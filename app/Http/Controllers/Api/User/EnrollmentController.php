<?php

namespace App\Http\Controllers\Api\User;

use App\Models\User;
use GuzzleHttp\Client;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EnrollmentController extends Controller
{
    public function initialzePayment(Request $request)
    {
        $this->validate($request, [
            'user_id' => 'required',
            'department' => 'required',
            'programme' => 'required',
            'gender' => 'nullable',
            'academic_session' => 'required',
            'paying_for' => 'required',
            'amount' => 'required'
        ]);

        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Student does not exist'], 404);
        }

        $callback_url = 'https://icritr.lms.unizik.edu.ng/verify-lms-user';
        $client = new Client();
        $response = $client->post('https://api.credocentral.com/transaction/initialize', [
            'headers' => [
                'Authorization' => '1PUB1309n0f51XpxIMIR0hvcEhH90u88HOl338',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'json' => [
                "customerFirstName" => $user->first_name,
                "customerLastName" => $user->last_name,
                "customerPhoneNumber" => $user->phone,
                "email" => $user->email,
                "amount" => $request->input('amount') * 100,
                'callback_url' => $callback_url,
            ],
        ]);
        $data = json_decode($response->getBody());

        $enroll = new Enrollment();
        $enroll->first_name = $user->first_name;
        $enroll->last_name = $user->last_name;
        $enroll->email = $user->email;
        $enroll->reg_number = $user->reg_number;
        $enroll->phone = $user->phone;
        $enroll->department = $request->input('department');
        $enroll->programme = $request->input('programme');
        $enroll->gender = $request->input('gender');
        $enroll->academic_session = $request->input('academic_session');
        $enroll->paying_for = $request->input('paying_for');
        $enroll->amount = $request->input('amount');
        $enroll->reference = $data->data->credoReference;
        $enroll->save();
        if ($enroll->save()) {
            // Redirect to Paystack payment page
            // return response()->json($data->data->authorization_url);
            return response()->json($data);
        } else {
            return response()->json([
                'status' => 'Failed',
                'Message' => 'Server Error'
            ], 500);
        }
    }

    public function verifyPayment()
    {
        $curl = curl_init();
        $reference = isset($_GET['transRef']) ? $_GET['transRef'] : '';
        if (!$reference) {
            return response()->json([
                'status' => 'Request Failed',
                'message' => 'No Reference Provided'
            ], 422);
        } else {
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.credocentral.com/transaction/" . $reference . "/verify",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "accept: application/json",
                    "authorization: 1PRI1309oRYtkTj556VvP5Fd0x4CZ3252gCmpl",
                    "cache-control: no-cache"
                ],
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            if ($err) {
                // there was an error contacting the Paystack API
                die('Curl returned error: ' . $err);
            }

            $callback = json_decode($response);

            if (!$callback->status) {
                // there was an error from the API
                die('API returned error: ' . $callback->message);
            }
            $status = $callback->status;
            $email = $callback->data->customerId;

            $detail = Enrollment::where(['reference' => $reference, 'email' => $email])->first();
            $DBreference = $detail->reference;

            if ($DBreference == $reference && $status == 200) {
                $updateDetail = Enrollment::where(['reference' => $reference, 'email' => $email])->update(['payment_status' => 1]);
                $updateUser = User::where(['email' => $email])->update(['is_enroll' => 1]);
                return response()->json([
                    'status' => 'Successful',
                    'message' => 'Payment was successful'
                ], 200);
            } else {
                return response()->json([
                    'status' => 'Failed',
                    'message' => 'Failed to confirm Payment'
                ], 401);
            }
        }
    }
}
