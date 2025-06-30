<?php

namespace App\Http\Controllers\Api\Free;

use App\Models\State;
use Illuminate\Http\Request;
use App\Models\LocalGovernment;
use App\Http\Controllers\Controller;

class FreeController extends Controller
{
    public function allState()
    {
        $states = State::get();

        // Check if the collection is empty
        if ($states->isEmpty()) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No State(s) found'
            ], 404);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "All State(s) fetched successfully",
            "data" => $states
        ], 200);
    }

    public function localgovernment(Request $request)
    {
        $lgas = LocalGovernment::where(['state_id' => $request->state_id])->get();

        // Check if the collection is empty
        if ($lgas->isEmpty()) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No Local Government(s) found'
            ], 404);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "All Local Government(s) fetched successfully",
            "data" => $lgas
        ], 200);
    }
}
