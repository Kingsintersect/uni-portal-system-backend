<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\State;
use App\Models\LocalGovernment;

class StateController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:admin')->except(['all_state', 'single_state']);
        $this->middleware('auth:api')->except(['all_state', 'single_state']);
    }

    public function add_state(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }
        $state = State::create([
            'name' => $request->name
        ]);
        return response()->json([
            'message' => 'State added successful',
            'data' => $state
        ], 201);
    }

    public function all_state()
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

    public function single_state($id)
    {
        $state = State::find($id);
        if ($state) {
            return response()->json(['state' => $state], 200);
        }
    }

    public function update_state(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }
        $state = State::find($request->id);
        if (!$state) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }
        $state->update([
            'name' => $request->name
        ]);
        return response()->json([
            'message' => 'State updated successful',
            'data' => $state
        ], 201);
    }

    public function delete_state(Request $request)
    {
        $state = State::find($request->id);
        if (!$state) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }

        // Delete the LGA records where the state_id matches the state's ID
        LocalGovernment::where('state_id', $state->id)->delete();

        // Delete the state itself
        $state->delete();

        // Return a success response
        return response()->json(['status' => 200, 'response' => 'Success', 'message' => 'State and associated LGAs deleted successfully'], 200);
    }
}
