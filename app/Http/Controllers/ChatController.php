<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClimateData;
use App\Models\GeoCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    public function getClimateData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kecamatan' => 'required|string',
            'tahun' => 'required|integer|min:2000|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $userInput = $request->input('kecamatan');
        $requestedYear = $request->input('tahun');
        $months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

        $responsePayload = null;
        $locationDetails = null;
        $userInputLower = strtolower($userInput);

        // --- DETEKSI INPUT: KOORDINAT (LAT, LON) ---
        if (preg_match('/^[-]?\d{1,3}\.\d+,\s*[-]?\d{1,3}\.\d+$/', $userInput)) {
            list($lat, $lon) = array_map('trim', explode(',', $userInput));

            // LANGKAH 1: Cari data iklim terdekat
            $nearestPoint = ClimateData::select('*', DB::raw("SQRT(POW(lat - ($lat), 2) + POW(lon - ($lon), 2)) AS distance"))
                ->orderBy('distance', 'asc')->first();

            if (!$nearestPoint) {
                return response()->json(['error' => 'Tidak ada data iklim yang ditemukan di database lokal.'], 404);
            }

            // LANGKAH 2: Ambil detail alamat dari cache atau API Nominatim
            $roundedLat = round($lat, 4);
            $roundedLon = round($lon, 4);
            $addressData = null;

            $cachedLocation = GeoCache::where('latitude', $roundedLat)
                ->where('longitude', $roundedLon)
                ->first();

            if ($cachedLocation) {
                $addressData = $cachedLocation->toArray();
            } else {
                try {
                    $geoResponse = Http::withHeaders([
                        'User-Agent' => 'Chat-bmkg-pt/1.0 (nurulhumam01@email.com)'
                    ])->timeout(15)->get("https://nominatim.openstreetmap.org/reverse", [
                        'lat' => $lat,
                        'lon' => $lon,
                        'format' => 'json',
                        'addressdetails' => 1
                    ]);

                    if ($geoResponse->successful() && isset($geoResponse->json()['address'])) {
                        $result = $geoResponse->json()['address'];

                        $desa = $result['village'] ?? $result['suburb'] ?? null;
                        $kecamatan = $result['county'] ?? $result['city_district'] ?? null;
                        $kabupaten = $result['city'] ?? $result['municipality'] ?? null;
                        $provinsi = $result['state'] ?? null;

                        $newCache = GeoCache::create([
                            'latitude' => $roundedLat,
                            'longitude' => $roundedLon,
                            'desa' => $desa,
                            'kecamatan' => $kecamatan,
                            'kabupaten' => $kabupaten,
                            'provinsi' => $provinsi,
                            'display_name' => $geoResponse->json()['display_name'] ?? "$desa, $kecamatan"
                        ]);

                        $addressData = $newCache->toArray();
                    }
                } catch (\Exception $e) {
                    Log::error('API Geocoding Nominatim gagal: ' . $e->getMessage());
                }
            }

            // LANGKAH 3: Tentukan nama lokasi
            $locationName = $addressData['desa'] ?? $nearestPoint->ID_KABKOTA_IKN;

            if ($addressData) {
                $locationDetails = [
                    'lat_input' => $lat,
                    'lon_input' => $lon,
                    'desa' => $addressData['desa'] ?? null,
                    'kecamatan' => $addressData['kecamatan'] ?? null,
                    'kabupaten' => $addressData['kabupaten'] ?? null,
                    'provinsi' => $addressData['provinsi'] ?? null,
                ];
            } else {
                $locationDetails = [
                    'lat_input' => $lat,
                    'lon_input' => $lon,
                    'provinsi' => $nearestPoint->ID_PROV38,
                    'kabupaten' => $nearestPoint->ID_KABKOTA_IKN
                ];
            }

            // Siapkan payload data iklim
            $dataValues = [];
            foreach ($months as $month) {
                $dataValues[] = (float) $nearestPoint->$month;
            }
            $responsePayload = ['name' => $locationName, 'data' => $dataValues];
        } else {
            // --- Logika untuk input berupa provinsi/kabupaten ---
            $avgProv = DB::table('id_grid_chprovkab_jawa')
                ->select('ID_PROV38', ...array_map(fn($m) => DB::raw("AVG(`$m`) as `$m`"), $months))
                ->where(DB::raw('LOWER(ID_PROV38)'), $userInputLower)
                ->groupBy('ID_PROV38')->first();

            if ($avgProv) {
                $dataValues = [];
                foreach ($months as $month) {
                    $dataValues[] = (float) $avgProv->$month;
                }
                $responsePayload = ['name' => $avgProv->ID_PROV38, 'data' => $dataValues];
            } else {
                $avgKab = DB::table('id_grid_chprovkab_jawa')
                    ->select('ID_KABKOTA_IKN', ...array_map(fn($m) => DB::raw("AVG(`$m`) as `$m`"), $months))
                    ->where(DB::raw('LOWER(ID_KABKOTA_IKN)'), $userInputLower)
                    ->groupBy('ID_KABKOTA_IKN')->first();

                if ($avgKab) {
                    $dataValues = [];
                    foreach ($months as $month) {
                        $dataValues[] = (float) $avgKab->$month;
                    }
                    $responsePayload = ['name' => $avgKab->ID_KABKOTA_IKN, 'data' => $dataValues];
                }
            }
        }

        if ($responsePayload) {
            return response()->json([
                'kecamatan' => $responsePayload['name'],
                'requested_year' => $requestedYear,
                'time_series' => null,
                'normal' => [
                    'labels' => $months,
                    'data' => $responsePayload['data'],
                    'chart_labels' => array_merge($months, $months),
                    'chart_data' => array_merge($responsePayload['data'], $responsePayload['data'])
                ],
                'location_details' => $locationDetails
            ]);
        }

        return response()->json(['error' => 'Data untuk "' . $userInput . '" tidak ditemukan, atau lokasi geografis tidak dapat ditentukan.'], 404);
    }
}
