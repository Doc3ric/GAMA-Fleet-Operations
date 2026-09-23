<?php

namespace App\Http\Requests;

use App\Models\AdvancedItinerary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdvancedItineraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isAdmin() || $this->user()->isOperator());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'itinerary_date' => ['required', 'date'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(AdvancedItinerary::STATUSES)],
            'legs' => ['required', 'array', 'min:1'],
            'legs.*.sort_order' => ['nullable', 'integer'],
            'legs.*.origin_location_id' => ['required', 'exists:locations,id'],
            'legs.*.starting_point_location_id' => ['required', 'exists:locations,id'],
            'legs.*.destination_location_id' => ['required', 'exists:locations,id'],
            'legs.*.distance_origin_to_start' => ['nullable', 'numeric', 'min:0'],
            'legs.*.distance_start_to_dest' => ['nullable', 'numeric', 'min:0'],
            'legs.*.total_distance' => ['nullable', 'numeric', 'min:0'],
            'legs.*.routing_source' => ['nullable', 'string', 'max:30'],
            'legs.*.purpose' => ['nullable', 'string', 'max:255'],
        ];
    }
}
