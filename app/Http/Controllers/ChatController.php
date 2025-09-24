<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClimateNormal;
use App\Models\ClimateAnalysis;
use App\Models\ClimatePrediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    private function getNormalData($lat, $lon, $userInput)
    {
        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

        if ($lat && $lon) {
            // Logika untuk input koordinat (sudah benar)
            $data = ClimateNormal::select('*', DB::raw("SQRT(POW(latitude - ($lat), 2) + POW(longitude - ($lon), 2)) AS distance"))
                ->orderBy('distance', 'asc')->first();
        } else {
            // DIUBAH: Logika pencarian berdasarkan nama diperbaiki agar menyertakan data bulanan
            $data = ClimateNormal::select(
                    'regency', 'province',
                    DB::raw('AVG(latitude) as latitude'), 
                    DB::raw('AVG(longitude) as longitude'),
                    ...array_map(fn($m) => DB::raw("AVG(`$m`) as `$m`"), $months)
                )
                ->where('province', 'LIKE', "%$userInput%")
                ->orWhere('regency', 'LIKE', "%$userInput%")
                ->groupBy('regency', 'province')
                ->first();
        }
        
        if (!$data) return null;

        $dataValues = [];
        foreach ($months as $month) {
            $dataValues[] = (float) $data->$month;
        }

        return [
            'locationName' => $data->regency ?: $data->province ?: $userInput,
            'data' => $dataValues,
            'coords' => ['lat' => $data->latitude, 'lon' => $data->longitude]
        ];
    }

    private function getAnalysisData($lat, $lon)
    {
        $periods = ClimateAnalysis::select('data_period')->distinct()
            ->orderBy('data_period', 'desc')->limit(3)->get()->pluck('data_period')->reverse()->values();

        if ($periods->count() < 2) return null;

        $labels = []; $data = [];
        foreach ($periods as $period) {
            $nearest = ClimateAnalysis::select('ch', DB::raw("SQRT(POW(latitude - ($lat), 2) + POW(longitude - ($lon), 2)) AS distance"))
                ->where('data_period', $period)->orderBy('distance', 'asc')->first();
            if ($nearest) { $labels[] = $period; $data[] = round($nearest->ch, 2); }
        }
        return count($labels) >= 2 ? ['labels' => $labels, 'data' => $data] : null;
    }

    private function getPredictionData($lat, $lon)
    {
        $periods = ClimatePrediction::select('prediction_period')->distinct()
            ->orderBy('prediction_period', 'asc')->get()->pluck('prediction_period');

        if ($periods->isEmpty()) return null;

        $labels = []; $data = [];
        foreach ($periods as $period) {
            $nearest = ClimatePrediction::select('val', DB::raw("SQRT(POW(latitude - ($lat), 2) + POW(longitude - ($lon), 2)) AS distance"))
                ->where('prediction_period', $period)->orderBy('distance', 'asc')->first();
            if ($nearest) { $labels[] = $period; $data[] = round($nearest->val, 2); }
        }
        return !empty($labels) ? ['labels' => $labels, 'data' => $data] : null;
    }

    public function getClimateData(Request $request)
    {
        $validator = Validator::make($request->all(), ['kecamatan' => 'required|string']);
        if ($validator->fails()) { return response()->json(['error' => $validator->errors()->first()], 400); }

        $userInput = $request->input('kecamatan');
        $targetLat = null; $targetLon = null; $locationName = $userInput;

        if (preg_match('/^[-]?\d{1,3}\.\d+,\s*[-]?\d{1,3}\.\d+$/', $userInput)) {
            list($lat, $lon) = array_map('trim', explode(',', $userInput));
            $normalData = $this->getNormalData($lat, $lon, null);
        } else {
            $normalData = $this->getNormalData(null, null, $userInput);
        }

        if (!$normalData) {
            return response()->json(['error' => 'Data Normal untuk "' . $userInput . '" tidak ditemukan.'], 404);
        }
        
        $targetLat = $normalData['coords']['lat'];
        $targetLon = $normalData['coords']['lon'];
        $locationName = $normalData['locationName'];

        $analysisData = $this->getAnalysisData($targetLat, $targetLon);
        $predictionData = $this->getPredictionData($targetLat, $targetLon);

        return response()->json([
            'locationName' => $locationName,
            'normal' => [
                'labels' => ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'],
                'data' => $normalData['data']
            ],
            'analysis' => $analysisData,
            'prediction' => $predictionData,
        ]);
    }
}