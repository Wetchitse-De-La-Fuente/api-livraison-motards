<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Course;
use App\Models\Notification;
use App\Models\Utilisateur;
use App\Events\ClientLocationUpdated;
use App\Events\CourseStatusChanged;
use App\Events\CourseUpdated;
use App\Events\UserNotificationCreated;

class CourseController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return response()->json(
                Course::with(['client', 'motard'])->latest()->get()
            );
        }

        if ($user->isMotard()) {
            return response()->json(
                Course::with(['client', 'motard'])
                    ->where(function ($query) use ($user) {
                        $query->where('status', 'en_attente')
                              ->orWhere('motard_id', $user->id);
                    })
                    ->latest()
                    ->get()
            );
        }

        return response()->json(
            Course::with(['client', 'motard'])
                ->where('client_id', $user->id)
                ->latest()
                ->get()
        );
    }

    public function show($id)
    {
        $course = Course::with(['client', 'motard'])->findOrFail($id);
        $user = auth()->user();

        if (
            !$user->isAdmin() &&
            $course->client_id !== $user->id &&
            $course->motard_id !== $user->id
        ) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        return response()->json($course);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->isClient()) {
            return response()->json(['message' => 'Seul un client peut créer une course'], 403);
        }

        $data = $request->validate([
            'pickup_address' => 'required|string|max:255',
            'delivery_address' => 'required|string|max:255',
            'pickup_latitude' => 'required|numeric',
            'pickup_longitude' => 'required|numeric',
            'delivery_latitude' => 'required|numeric',
            'delivery_longitude' => 'required|numeric',
            'description' => 'nullable|string',
            'waiting_minutes' => 'nullable|integer|min:0',
            'outside_city' => 'nullable|boolean',
        ]);

        $route = $this->getRouteFromRoutingServer(
            (float) $data['pickup_latitude'],
            (float) $data['pickup_longitude'],
            (float) $data['delivery_latitude'],
            (float) $data['delivery_longitude']
        );

        $distanceKm = $route['distance_km'];
        $durationMin = $route['duration_min'];

        $waitingMinutes = $data['waiting_minutes'] ?? 0;
        $pickupFee = 0;

        $estimatedPrice = $this->calculatePrice(
            $distanceKm,
            $durationMin,
            $waitingMinutes,
            $pickupFee,
            $data['outside_city'] ?? false
        );

        $course = Course::create([
            'client_id' => $user->id,
            'motard_id' => null,
            'pickup_address' => $data['pickup_address'],
            'delivery_address' => $data['delivery_address'],
            'pickup_latitude' => $data['pickup_latitude'],
            'pickup_longitude' => $data['pickup_longitude'],
            'delivery_latitude' => $data['delivery_latitude'],
            'delivery_longitude' => $data['delivery_longitude'],
            'description' => $data['description'] ?? null,
            'distance_km' => round($distanceKm, 2),
            'duration_min' => $durationMin,
            'waiting_minutes' => $waitingMinutes,
            'pickup_fee' => $pickupFee,
            'estimated_price' => $estimatedPrice,
            'final_price' => $estimatedPrice,
            'status' => 'en_attente',
        ]);

        $course->load(['client', 'motard']);
        broadcast(new CourseUpdated($course))->toOthers();

        $motardsOnline = Utilisateur::where('role', 'motard')
            ->where('is_online', true)
            ->where('is_blocked', false)
            ->get();

        foreach ($motardsOnline as $motard) {
            $notification = Notification::create([
                'utilisateur_id' => $motard->id,
                'message' => 'Nouvelle course disponible.',
                'type' => 'nouvelle_course',
                'is_read' => false,
            ]);

            broadcast(new UserNotificationCreated($notification))->toOthers();
        }

        return response()->json($course, 201);
    }

    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);
        $user = auth()->user();

        if (!$user->isAdmin() && $course->client_id !== $user->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        if ($course->status !== 'en_attente') {
            return response()->json(['message' => 'Impossible de modifier cette course'], 422);
        }

        $data = $request->validate([
            'pickup_address' => 'sometimes|string|max:255',
            'delivery_address' => 'sometimes|string|max:255',
            'pickup_latitude' => 'sometimes|numeric',
            'pickup_longitude' => 'sometimes|numeric',
            'delivery_latitude' => 'sometimes|numeric',
            'delivery_longitude' => 'sometimes|numeric',
            'description' => 'nullable|string',
            'waiting_minutes' => 'sometimes|integer|min:0',
            'outside_city' => 'nullable|boolean',
        ]);

        $course->update($data);

        $pickupLat = (float) ($data['pickup_latitude'] ?? $course->pickup_latitude);
        $pickupLng = (float) ($data['pickup_longitude'] ?? $course->pickup_longitude);
        $deliveryLat = (float) ($data['delivery_latitude'] ?? $course->delivery_latitude);
        $deliveryLng = (float) ($data['delivery_longitude'] ?? $course->delivery_longitude);

        $route = $this->getRouteFromRoutingServer($pickupLat,$pickupLng,$deliveryLat,$deliveryLng);

        $distanceKm = $route['distance_km'];
        $durationMin = $route['duration_min'];

        $waitingMinutes = $data['waiting_minutes'] ?? $course->waiting_minutes ?? 0;
        $pickupFee = $course->pickup_fee ?? 0;

        $price = $this->calculatePrice(
            $distanceKm,
            $durationMin,
            $waitingMinutes,
            $pickupFee,
            $data['outside_city'] ?? false
        );

        $course->update([
            'distance_km' => round($distanceKm, 2),
            'duration_min' => $durationMin,
            'estimated_price' => $price,
            'final_price' => $price,
        ]);

        $course->load(['client', 'motard']);
        broadcast(new CourseUpdated($course))->toOthers();

        return response()->json([
            'message' => 'Course mise à jour',
            'course' => $course->fresh(['client', 'motard'])
        ]);
    }

    public function destroy($id)
    {
        $course = Course::findOrFail($id);
        $user = auth()->user();

        if (!$user->isAdmin() && $course->client_id !== $user->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $course->delete();

        return response()->json(null, 204);
    }

    public function assignMotard($id)
    {
        $course = Course::findOrFail($id);
        $user = auth()->user();

        if (!$user->isMotard()) {
            return response()->json(['message' => 'Seul un motard peut accepter une course'], 403);
        }

        if (!$user->is_online) {
            return response()->json(['message' => 'Le motard doit être en ligne'], 422);
        }

        if ($course->status !== 'en_attente' || $course->motard_id !== null) {
            return response()->json(['message' => 'Cette course n’est plus disponible'], 422);
        }

        $course->update([
            'motard_id' => $user->id,
            'status' => 'acceptee',
        ]);

        $course->load(['client', 'motard']);
        broadcast(new CourseStatusChanged($course))->toOthers();

        $notification = Notification::create([
            'utilisateur_id' => $course->client_id,
            'message' => 'Votre commande a été acceptée par un motard.',
            'type' => 'course_acceptee',
            'is_read' => false,
        ]);

        broadcast(new UserNotificationCreated($notification))->toOthers();

        return response()->json([
            'message' => 'Course acceptée avec succès',
            'course' => $course->fresh(['client', 'motard'])
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $course = Course::findOrFail($id);
        $user = auth()->user();

        $data = $request->validate([
            'status' => 'required|in:acceptee,recuperation_en_cours,en_livraison,livree,annulee'
        ]);

        $newStatus = $data['status'];

        if ($user->isMotard()) {
            if ($course->motard_id !== $user->id) {
                return response()->json(['message' => 'Cette course ne vous appartient pas'], 403);
            }

            $allowedTransitions = [
                'acceptee' => ['recuperation_en_cours', 'annulee'],
                'recuperation_en_cours' => ['en_livraison', 'annulee'],
                'en_livraison' => ['livree', 'annulee'],
            ];

            if (
                !isset($allowedTransitions[$course->status]) ||
                !in_array($newStatus, $allowedTransitions[$course->status])
            ) {
                return response()->json(['message' => 'Transition de statut invalide'], 422);
            }
        } elseif ($user->isClient()) {
            if ($course->client_id !== $user->id) {
                return response()->json(['message' => 'Accès refusé'], 403);
            }

            if (!($course->status === 'en_attente' && $newStatus === 'annulee')) {
                return response()->json(['message' => 'Le client ne peut annuler qu’une course en attente'], 422);
            }
        } elseif (!$user->isAdmin()) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $payload = ['status' => $newStatus];

        if (in_array($newStatus, ['livree', 'annulee'])) {
            $payload['client_location_shared'] = false;
            $payload['client_current_latitude'] = null;
            $payload['client_current_longitude'] = null;
        }

        $course->update($payload);
        $course->load(['client', 'motard']);

        broadcast(new CourseStatusChanged($course))->toOthers();

        if ($course->client_id) {
            $messages = [
                'recuperation_en_cours' => 'Le motard est en route vers le point de récupération.',
                'en_livraison' => 'Votre commande est en livraison.',
                'livree' => 'Votre commande a été livrée.',
                'annulee' => 'Votre commande a été annulée.',
            ];

            if (isset($messages[$newStatus])) {
                $notification = Notification::create([
                    'utilisateur_id' => $course->client_id,
                    'message' => $messages[$newStatus],
                    'type' => $newStatus,
                    'is_read' => false,
                ]);

                broadcast(new UserNotificationCreated($notification))->toOthers();
            }
        }

        return response()->json([
            'message' => 'Statut mis à jour',
            'course' => $course->fresh(['client', 'motard'])
        ]);
    }

    public function updateClientLocation(Request $request, $id)
    {
        $course = Course::findOrFail($id);
        $user = auth()->user();

        if (!$user->isClient() || $course->client_id !== $user->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        if (in_array($course->status, ['livree', 'annulee'])) {
            return response()->json([
                'message' => 'Impossible de partager la position pour cette course'
            ], 422);
        }

        $data = $request->validate([
            'client_location_shared' => 'required|boolean',
            'client_current_latitude' => 'nullable|numeric',
            'client_current_longitude' => 'nullable|numeric',
        ]);

        if ($data['client_location_shared']) {
            if (
                !isset($data['client_current_latitude']) ||
                !isset($data['client_current_longitude'])
            ) {
                return response()->json([
                    'message' => 'Latitude et longitude du client sont requises'
                ], 422);
            }

            $course->update([
                'client_location_shared' => true,
                'client_current_latitude' => $data['client_current_latitude'],
                'client_current_longitude' => $data['client_current_longitude'],
            ]);

            $course->load(['client', 'motard']);
            broadcast(new ClientLocationUpdated($course))->toOthers();

            return response()->json([
                'message' => 'Position du client partagée avec succès',
                'course' => $course->fresh(['client', 'motard'])
            ]);
        }

        $course->update([
            'client_location_shared' => false,
            'client_current_latitude' => null,
            'client_current_longitude' => null,
        ]);

        $course->load(['client', 'motard']);
        broadcast(new ClientLocationUpdated($course))->toOthers();

        return response()->json([
            'message' => 'Partage de position désactivé',
            'course' => $course->fresh(['client', 'motard'])
        ]);
    }

    public function clientLocation($id)
    {
        $course = Course::with(['client', 'motard'])->findOrFail($id);
        $user = auth()->user();

        $isAllowed =
            $user->isAdmin() ||
            $course->client_id === $user->id ||
            ($user->isMotard() && $course->motard_id === $user->id);

        if (!$isAllowed) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        if (!$course->client_location_shared) {
            return response()->json([
                'message' => 'Le client n’a pas partagé sa position',
                'client_location_shared' => false
            ]);
        }

        return response()->json([
            'client_location_shared' => true,
            'client_current_latitude' => $course->client_current_latitude,
            'client_current_longitude' => $course->client_current_longitude,
            'client_id' => $course->client_id,
            'motard_id' => $course->motard_id,
            'course_id' => $course->id,
        ]);
    }

    public function estimatePrice(Request $request)
    {
        $data = $request->validate([
            'pickup_latitude' => 'required|numeric',
            'pickup_longitude' => 'required|numeric',
            'delivery_latitude' => 'required|numeric',
            'delivery_longitude' => 'required|numeric',
            'waiting_minutes' => 'nullable|integer|min:0',
            'pickup_fee' => 'nullable|numeric|min:0',
            'outside_city' => 'nullable|boolean',
        ]);

        $route = $this->getRouteFromRoutingServer(
            (float) $data['pickup_latitude'],
            (float) $data['pickup_longitude'],
            (float) $data['delivery_latitude'],
            (float) $data['delivery_longitude']
        );

        $distanceKm = $route['distance_km'];
        $durationMin = $route['duration_min'];
        $waitingMinutes = $data['waiting_minutes'] ?? 0;
        $pickupFee = $data['pickup_fee'] ?? 0;

        $estimatedPrice = $this->calculatePrice(
            $distanceKm,
            $durationMin,
            $waitingMinutes,
            $pickupFee,
            $data['outside_city'] ?? false
        );

        return response()->json([
            'distance_km' => round($distanceKm, 2),
            'duration_min' => round($durationMin, 2),
            'waiting_minutes' => $waitingMinutes,
            'pickup_fee' => $pickupFee,
            'estimated_price' => $estimatedPrice,
        ]);
    }

    protected function calculatePrice(
        float $distanceKm,
        float $durationMin,
        int $waitingMinutes = 0,
        float $pickupFee = 0,
        bool $outsideCity = false
    ): float {
        $basePrice = 570;
        $baseDistance = 1.1;
        $baseMinutes = 4;

        $kmRate = $outsideCity ? 150 : 77;
        $minuteRate = 30;
        $waitingFree = 3;
        $waitingRate = 31;

        $price = $basePrice;

        if ($distanceKm > $baseDistance) {
            $price += ($distanceKm - $baseDistance) * $kmRate;
        }

        if ($durationMin > $baseMinutes) {
            $price += ($durationMin - $baseMinutes) * $minuteRate;
        }

        if ($waitingMinutes > $waitingFree) {
            $price += ($waitingMinutes - $waitingFree) * $waitingRate;
        }

        $price += $pickupFee;

        return ceil($price / 100) * 100;
    }

    protected function calculateDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    protected function estimateDurationFromDistance(float $distanceKm): int
    {
        $avgSpeedKmH = 22;
        $durationHours = $distanceKm / $avgSpeedKmH;
        $durationMinutes = round($durationHours * 60);

        return max(4, (int) $durationMinutes);
    }

    private function getRouteFromRoutingServer(
        float $pickupLat,
        float $pickupLng,
        float $deliveryLat,
        float $deliveryLng
    ): array {
        $baseUrl = rtrim(config('services.routing.url'), '/');

        // OSRM attend : longitude,latitude
        $url = "{$baseUrl}/route/v1/driving/{$pickupLng},{$pickupLat};{$deliveryLng},{$deliveryLat}";

        try {
            $response = Http::timeout(5)->get($url, [
                'overview' => 'false',
                'alternatives' => 'false',
                'steps' => 'false',
            ]);

            if (!$response->successful()) {
                throw new \Exception("Serveur de route indisponible");
            }

            $data = $response->json();

            if (
                !isset($data['routes']) ||
                !is_array($data['routes']) ||
                count($data['routes']) === 0
            ) {
                throw new \Exception("Aucun trajet trouvé");
            }

            $route = $data['routes'][0];

            return [
                // OSRM retourne la distance en mètres
                'distance_km' => round(((float) $route['distance']) / 1000, 2),

                // OSRM retourne la durée en secondes
                'duration_min' => round(((float) $route['duration']) / 60, 2),
            ];
        } catch (\Throwable $e) {
            // Sécurité : si le serveur de route tombe, on garde une solution de secours
            $distanceKm = $this->calculateDistanceKm(
                $pickupLat,
                $pickupLng,
                $deliveryLat,
                $deliveryLng
            );

            return [
                'distance_km' => round($distanceKm, 2),
                'duration_min' => $this->estimateDurationFromDistance($distanceKm),
            ];
        }
    }
}