<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\State;
use App\Models\LocalGovernment;
use Illuminate\Support\Facades\DB;

class LgaController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:admin')->except(['all_lga', 'single_lga']);
        $this->middleware('auth:api')->except(['all_lga', 'single_lga']);
    }

    public function add_lga(Request $request)
    {
        try {
            // Validate the request data
            $validated = $request->validate([
                'state_id' => 'required|exists:states,id', // Ensure the state_id exists in the states table
                'lgas' => 'required|array', // Ensure lgas is an array
                'lgas.*' => 'required|string|max:255', // Each LGA should be a string with a max length of 255
            ]);

            // Array to store the created LGAs
            $createdLGAs = [];

            // Loop through the lgas and insert them into the LGA table
            foreach ($validated['lgas'] as $lgaName) {
                // Create and store the LGA
                $lga = LocalGovernment::create([
                    'state_id' => $validated['state_id'],
                    'name' => $lgaName,
                ]);

                // Add the created LGA to the array
                $createdLGAs[] = $lga;
            }

            // Return a success response with the created data
            return response()->json([
                'status' => 201,
                'message' => 'LGAs created successfully',
                'data' => $createdLGAs // Return the created LGAs
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors (including the case when state_id does not exist)
            return response()->json([
                'status' => 422,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        }
    }


    public function all_lga(Request $request)
    {

        $IS_LGA_NAME = false;
        if ($request->state_id) {
            if (!is_numeric($request->state_id)) {
                $IS_LGA_NAME = true;
                $request->state_id = trim(str_replace(' state', '', strtolower($request->state_id)));
            }
        }

        // Validate state_id if provided
        // $validator = Validator::make($request->all(), [
        //     'state_id' => 'nullable|exists:states,id|exists:states,name', // Check if state_id exists in the states table
        // ]);


        $validator = Validator::make($request->all(), [
            'state_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $value = trim(str_replace(' state', '', strtolower($value)));
                    // Check if value is numeric (ID) and exists in `states.id`
                    if ($value !== null) {
                        if (is_numeric($value)) {
                            if (!DB::table('states')->where('id', $value)->exists()) {
                                return $fail('The selected state ID is invalid.');
                            }
                        }
                        // Check if value is a string (state name) and exists in `states.name`
                        else {
                            if (!DB::table('states')->where('name', $value)->exists()) {
                                return $fail('The selected state name is invalid.');
                            }
                        }
                    }
                },
            ],
        ]);


        // Return validation errors if any
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'response' => 'Validation Error',
                'message' => $validator->errors()
            ], 422);
        }

        // Fetch lgas based on faculty_id if provided, otherwise fetch all departments

        $lgas = $request->state_id
            ? ($IS_LGA_NAME ? LocalGovernment::join('states', 'local_governments.state_id', '=', 'states.id')
                ->where('states.name', $request->state_id)
                ->select('local_governments.*') // Select only LocalGovernment columns
                ->get() :
                LocalGovernment::where('state_id', $request->state_id)->get())
            : LocalGovernment::all();

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "All Local Government(s) fetched successfully",
            "data" => $lgas
        ], 200);
    }

    public function single_lga($id)
    {
        $lga = LocalGovernment::find($id);
        if ($lga) {
            return response()->json(['lga' => $lga], 200);
        }
    }

    public function update_lga(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'state_id' => ['required'],
            'lga' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }
        $state = LocalGovernment::find($request->id);
        if (!$state) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }
        $state->update([
            'name' => $request->lga,
            'state_id' => $request->state_id,
        ]);
        return response()->json([
            'message' => 'Local government updated successful',
            'data' => $state
        ], 201);
    }

    public function delete_lga(Request $request)
    {
        $state = LocalGovernment::find($request->id);
        if (!$state) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }

        // Delete the state itself
        $state->delete();

        // Return a success response
        return response()->json(['status' => 200, 'response' => 'Success', 'message' => 'LGAs deleted successfully'], 200);
    }
}
