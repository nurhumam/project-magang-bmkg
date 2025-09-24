<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    // --- FUNGSI-FUNGSI HELPER UNTUK SETIAP JENIS DATA ---

    private function getNormalData($lat, $lon)
    {
        $nearest = DB::table('climate_normals')
            ->select('*', DB::raw("SQRT(POW(latitude - ($lat), 2) + POW(longitude - ($lon), 2)) AS distance"))
            ->orderBy('distance', 'asc')
            ->first();

        if (!$nearest)
            return null;

        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        $dataValues = [];
        foreach ($months as $month) {
            $dataValues[] = (float) $nearest->$month;
        }

        return [
            'locationName' => $nearest->regency ?: $nearest->province,
            'data' => $dataValues
        ];
    }

    private function getAnalysisData($lat, $lon)
    {
        $periods = DB::table('climate_analyses')->select('data_period')->distinct()
            ->orderBy('data_period', 'desc')->limit(3)->get()->pluck('data_period')->reverse()->values();

        if ($periods->count() < 2)
            return null;

        $comparisonLabels = [];
        $comparisonData = [];

        foreach ($periods as $period) {
            $nearest = DB::table('climate_analyses')
                ->select('ch', DB::raw("SQRT(POW(latitude - ($lat), 2) + POW(longitude - ($lon), 2)) AS distance"))
                ->where('data_period', $period)->orderBy('distance', 'asc')->first();

            if ($nearest) {
                $comparisonLabels[] = $period;
                $comparisonData[] = round($nearest->ch, 2);
            }
        }

        return count($comparisonLabels) >= 2 ? ['labels' => $comparisonLabels, 'data' => $comparisonData] : null;
    }

    private function getPredictionData($lat, $lon)
    {
        $periods = DB::table('climate_predictions')->select('prediction_period')->distinct()
            ->orderBy('prediction_period', 'asc')->get()->pluck('prediction_period');

        if ($periods->isEmpty())
            return null;

        $predictionLabels = [];
        $predictionData = [];

        foreach ($periods as $period) {
            $nearest = DB::table('climate_predictions')
                ->select('val', DB::raw("SQRT(POW(latitude - ($lat), 2) + POW(longitude - ($lon), 2)) AS distance"))
                ->where('prediction_period', $period)->orderBy('distance', 'asc')->first();

            if ($nearest) {
                $predictionLabels[] = $period;
                $predictionData[] = round($nearest->val, 2);
            }
        }

        return !empty($predictionLabels) ? ['labels' => $predictionLabels, 'data' => $predictionData] : null;
    }


    // --- ENDPOINT API UTAMA ---

    public function getClimateData(Request $request)
    {
        $validator = Validator::make($request->all(), ['kecamatan' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $userInput = $request->input('kecamatan');
        $targetLat = null;
        $targetLon = null;
        $locationName = $userInput;

        // Tentukan koordinat target
        if (preg_match('/^[-]?\d{1,3}\.\d+,\s*[-]?\d{1,3}\.\d+$/', $userInput)) {
            list($lat, $lon) = array_map('trim', explode(',', $userInput));
            $targetLat = $lat;
            $targetLon = $lon;
        } else {
            $avgLocation = DB::table('climate_normals')
                ->select(DB::raw('AVG(latitude) as lat, AVG(longitude) as lon'))
                ->where(function ($query) use ($userInput) {
                    $query->where('province', 'LIKE', "%$userInput%")
                        ->orWhere('regency', 'LIKE', "%$userInput%");
                })->first();
            if ($avgLocation && $avgLocation->lat) {
                $targetLat = $avgLocation->lat;
                $targetLon = $avgLocation->lon;
            }
        }

        if (!$targetLat) {
            return response()->json(['error' => 'Lokasi tidak dapat ditentukan.'], 404);
        }

        // Panggil semua helper untuk mendapatkan data
        $normalData = $this->getNormalData($targetLat, $targetLon);
        $analysisData = $this->getAnalysisData($targetLat, $targetLon);
        $predictionData = $this->getPredictionData($targetLat, $targetLon);

        if ($normalData) {
            $locationName = $normalData['locationName'];
        }

        if (!$normalData && !$analysisData && !$predictionData) {
            return response()->json(['error' => 'Data untuk "' . $userInput . '" tidak ditemukan.'], 404);
        }

        return response()->json([
            'locationName' => $locationName,
            'normal' => $normalData ? [
                'labels' => ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'],
                'data' => $normalData['data']
            ] : null,
            'analysis' => $analysisData,
            'prediction' => $predictionData,
        ]);
    }
}