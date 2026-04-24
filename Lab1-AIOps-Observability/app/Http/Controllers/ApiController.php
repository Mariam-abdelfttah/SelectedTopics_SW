<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ApiController extends Controller
{
    public function normal()
    {
        return response()->json(['status' => 'success', 'message' => 'Normal response', 'timestamp' => now()]);
    }
    
    public function slow(Request $request)
    {
        $hardMode = $request->query('hard', false);
        $sleepTime = $hardMode ? rand(5, 7) : rand(1, 2);
        sleep($sleepTime);
        
        return response()->json([
            'status' => 'success',
            'message' => "Slow response after {$sleepTime} seconds",
            'hard_mode' => (bool)$hardMode,
            'sleep_seconds' => $sleepTime
        ]);
    }
    
    public function error()
    {
        $errorType = rand(1, 3);
        if ($errorType === 1) abort(400, 'Bad Request');
        if ($errorType === 2) abort(500, 'Internal Server Error');
        abort(503, 'Service Unavailable');
    }
    
    public function random()
    {
        $random = rand(1, 10);
        if ($random <= 7) return $this->normal();
        if ($random <= 9) return $this->slow(new Request());
        return $this->error();
    }
    
    public function db(Request $request)
    {
        $fail = $request->query('fail', false);
        
        try {
            if ($fail) {
                DB::table('non_existent_table')->get();
            } else {
                DB::statement('CREATE TABLE IF NOT EXISTS telemetry_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT)');
                $result = DB::table('telemetry_users')->count();
            }
            return response()->json(['status' => 'success', 'data' => $result ?? 0]);
        } catch (\Exception $e) {
            Log::error('Database error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function validate(Request $request)
    {
        $rules = ['email' => 'required|email', 'age' => 'required|integer|min:18|max:60'];
        $validator = validator($request->all(), $rules);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        return response()->json(['status' => 'success', 'message' => 'Validation passed', 'data' => $request->all()]);
    }
}