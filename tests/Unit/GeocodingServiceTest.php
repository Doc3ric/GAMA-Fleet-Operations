<?php

namespace Tests\Unit;

use App\Models\Location;
use App\Models\User;
use App\Services\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GeocodingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeocodingService;
    }

    public function test_parses_decimal_coordinates_with_comma(): void
    {
        $parsed = $this->service->parseCoordinates('14.852345, 120.816412');

        $this->assertNotNull($parsed);
        $this->assertEquals(14.852345, $parsed['latitude']);
        $this->assertEquals(120.816412, $parsed['longitude']);
    }

    public function test_parses_decimal_coordinates_with_space(): void
    {
        $parsed = $this->service->parseCoordinates('14.852345 120.816412');

        $this->assertNotNull($parsed);
        $this->assertEquals(14.852345, $parsed['latitude']);
        $this->assertEquals(120.816412, $parsed['longitude']);
    }

    public function test_parses_dms_coordinates(): void
    {
        $parsed = $this->service->parseCoordinates('14°51\'08.3"N 120°48\'59.0"E');

        $this->assertNotNull($parsed);
        $this->assertEqualsWithDelta(14.8523056, $parsed['latitude'], 0.0001);
        $this->assertEqualsWithDelta(120.8163889, $parsed['longitude'], 0.0001);
    }

    public function test_parses_google_maps_at_coordinates(): void
    {
        $url = 'https://www.google.com/maps/place/Bocaue,+Bulacan/@14.802148,120.9254397,14z/data=!3m1!4b1';
        $parsed = $this->service->parseCoordinates($url);

        $this->assertNotNull($parsed);
        $this->assertEquals(14.802148, $parsed['latitude']);
        $this->assertEquals(120.9254397, $parsed['longitude']);
    }

    public function test_parses_google_maps_q_parameter(): void
    {
        $url = 'https://maps.google.com/?q=14.852345,120.816412';
        $parsed = $this->service->parseCoordinates($url);

        $this->assertNotNull($parsed);
        $this->assertEquals(14.852345, $parsed['latitude']);
        $this->assertEquals(120.816412, $parsed['longitude']);
    }

    public function test_rejects_out_of_range_coordinates(): void
    {
        $this->assertNull($this->service->parseCoordinates('95.1234, 120.8164'));
        $this->assertNull($this->service->parseCoordinates('14.1234, 185.8164'));
    }

    public function test_detects_google_maps_urls(): void
    {
        $this->assertTrue($this->service->isGoogleMapsUrl('https://maps.app.goo.gl/abcdef123'));
        $this->assertTrue($this->service->isGoogleMapsUrl('https://goo.gl/maps/xyz789'));
        $this->assertTrue($this->service->isGoogleMapsUrl('https://www.google.com/maps/@14.8,120.8,15z'));
        $this->assertFalse($this->service->isGoogleMapsUrl('https://example.com/map'));
    }

    public function test_search_returns_direct_coordinate_result(): void
    {
        $results = $this->service->search('14.852345, 120.816412');

        $this->assertNotEmpty($results);
        $this->assertEquals('coordinate', $results[0]['source']);
        $this->assertEquals(14.852345, $results[0]['latitude']);
        $this->assertEquals(120.816412, $results[0]['longitude']);
    }

    public function test_search_matches_local_directory_locations(): void
    {
        $user = User::factory()->admin()->create();

        Location::create([
            'code' => 'TEST-FARM-99',
            'official_name' => 'SAN RAFAEL BREEDING FARM',
            'type' => Location::TYPE_FARM,
            'latitude' => 14.9500000,
            'longitude' => 120.9500000,
            'address' => 'San Rafael, Bulacan',
            'status' => Location::STATUS_ACTIVE,
            'created_by' => $user->id,
        ]);

        $results = $this->service->search('BREEDING FARM');

        $this->assertNotEmpty($results);
        $local = collect($results)->firstWhere('source', 'local_directory');
        $this->assertNotNull($local);
        $this->assertStringContainsString('SAN RAFAEL BREEDING FARM', $local['title']);
    }

    public function test_search_handles_empty_query(): void
    {
        $this->assertEmpty($this->service->search(''));
        $this->assertEmpty($this->service->search('   '));
    }
}
