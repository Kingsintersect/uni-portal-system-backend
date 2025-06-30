<?php

namespace App\Http\Controllers\Api\Application;

use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Mail\WelcomeMail;
use App\Mail\AdmissionMail;
use App\Models\PaymentLog;
use Illuminate\Http\Request;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\ApplicationForm;
use App\Models\ApplicationPayment;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use App\Services\PDFService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class ApplicationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['initializePayment', 'verifyPayment', 'login', 'uploadPassport', 'uploadFirstSittingResult', 'uploadSecondSittingResult', 'testMail']);
    }

    public function initializePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'other_name' => ['nullable'],
            'faculty_id' => ['required', 'integer'],
            'department_id' => ['required', 'integer'],
            'nationality' => ['required'],
            'state' => ['required'],
            'phone_number' => ['required'],
            'email' => ['required', 'email'],
            // 'password' => [
            //     'required',
            //     'confirmed',
            //     'min:8',
            //     'regex:/[a-z]/',
            //     'regex:/[A-Z]/',
            //     'regex:/[0-9]/',
            //     'regex:/[@$!%*#?&]/',
            // ],
            'amount' => ['required'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        $callback_url = env('FRONTEND_BASE_URL', 'https://odl-esut.qverselearning.org') . '/admission/payments/verify-admission';
        $client = new Client();
        $response = $client->post('https://api.credocentral.com/transaction/initialize', [
            'headers' => [
                'Authorization' => "1PUB1309n0f51XpxIMIR0hvcEhH90u88HOl338",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'json' => [
                "customerFirstName" => $request->first_name,
                "customerLastName" => $request->last_name,
                "customerPhoneNumber" => $request->phone_number,
                "email" => $request->email,
                "amount" => $request->amount * 100,
                "callback_url" => $callback_url,
                "serviceCode" => "0013098YA2VG",
                "metadata" => [
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'other_name' => $request->other_name,
                    'faculty_id' => $request->faculty_id,
                    'department_id' => $request->department_id,
                    'nationality' => $request->nationality,
                    'state' => $request->state,
                    'phone_number' => $request->phone_number,
                    'email' => $request->email,
                    'password' => $request->password,
                    'amount' => $request->amount,
                ]
            ],
        ]);
        $data = json_decode($response->getBody());

        $application = new User();
        $application->first_name = $request->first_name;
        $application->last_name = $request->last_name;
        $application->other_name = $request->other_name;
        $application->email = $request->email;
        $application->faculty_id = $request->faculty_id;
        $application->phone_number = $request->phone_number;
        $application->department_id = $request->department_id;
        $application->nationality = $request->nationality;
        $application->state = $request->state;
        $application->password = bcrypt($request->password);
        $application->amount = $request->amount;
        $application->reference = $data->data->credoReference;
        $application->save();
        if ($application->save()) {
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

            $detail = ApplicationPayment::where(['reference' => $reference, 'email' => $email])->first();
            if (!$detail) {
                return response()->json([
                    'status' => 'Failed',
                    'message' => 'Transaction Details Not Found'
                ], 404);
            }
            $DBreference = $detail->reference;

            if ($DBreference == $reference && $status == 200) {
                $updateDetail = ApplicationPayment::where(['reference' => $reference, 'email' => $email])->update(['application_payment_status' => true]);
                PaymentLog::create([
                    'user_id' => $detail->id,
                    'payment_type' => 'Application Fee',
                    'amount' => $this->getInsightTagValue('amount', $callback->data->metadata),
                    'reference' => $reference,
                    'status' => 'Paid'
                ]);
                $url = "https://google.com";
                $password = $this->getInsightTagValue('password', $callback->data->metadata);
                Mail::to($detail->email)->send(new WelcomeMail($detail, $password, $url));
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

    public function login(Request $request)
    {
        $fieldCol = (filter_var($request->reference, FILTER_VALIDATE_EMAIL)) ? 'email' : 'reference';

        $credentials = [
            $fieldCol => $request->input('reference'),
            'password' => $request->input('password')
        ];

        $allPassed = false;
        $_guard = 'api';
        // Attempt Users T
        // Attempt Applications T
        if (!$token = Auth::guard('api')->attempt($credentials)) {
            // return response()->json(['status' => 403, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 403);
        } else {
            $user = ApplicationPayment::where($fieldCol, $request->reference)->first();
            $allPassed = true;
        }
        if (!$allPassed) {
            if (!$token = Auth::guard('user')->attempt($credentials)) {
                return response()->json(['status' => 403, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 403);
            }
            $user = User::where($fieldCol, $request->reference)->first();
            $_guard = 'user';
        }

        if ($user->role == 'STUDENT') {
            $payment_status = $user->application_payment_status;
            if ($payment_status == false) {
                return response()->json(['status' => 403, 'response' => 'Invalid Reference', 'message' => 'Invalid Reference'], 403);
            }
        }

        return $this->createToken($token, $_guard);
    }

    public function createToken($token, $guard = 'api')
    {
        if ($guard == 'user') {
            $user = User::find(auth('user')->user()->id);
        } else {
            $user = ApplicationPayment::find(auth($guard)->user()->id);
        }
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user
        ]);
    }



    public function applicationForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gender' => ['required'],
            'lga' => ['required'],
            'hometown' => ['required'],
            'hometown_address' => ['required'],
            'contact_address' => ['required'],
            'religion' => ['required'],
            'disability' => ['required'],
            'dob' => ['required'],
            'other_disability' => ['nullable'],
            'sponsor_name' => ['required'],
            'sponsor_relationship' => ['required'],
            'sponsor_phone_number' => ['required'],
            'sponsor_email' => ['required'],
            'sponsor_contact_address' => ['required'],
            'awaiting_result' => ['boolean'],
            'first_sitting' => ['nullable'],
            'second_sitting' => ['nullable'],
            'image_url' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        // Convert array to JSON string
        $firstSitting = json_encode($request->first_sitting);
        $secondSitting = $request->second_sitting ? json_encode($request->second_sitting) : null;
        try {
            $application_form = ApplicationForm::create([
                'user_id' => auth('api')->user()->id,
                'gender' => $request->gender,
                'lga' => $request->lga,
                'hometown' => $request->hometown,
                'hometown_address' => $request->hometown_address,
                'contact_address' => $request->contact_address,
                'religion' => $request->religion,
                'disability' => $request->disability,
                'dob' => $request->dob,
                'other_disability' => $request->other_disability,
                'sponsor_name' => $request->sponsor_name,
                'sponsor_relationship' => $request->sponsor_relationship,
                'sponsor_phone_number' => $request->sponsor_phone_number,
                'sponsor_email' => $request->sponsor_email,
                'sponsor_contact_address' => $request->sponsor_contact_address,
                'awaiting_result' => $request->awaiting_result,
                'first_sitting' => $firstSitting,
                'second_sitting' => $secondSitting,
                'passport' => $request->image_url
            ]);
            if ($application_form) {
                ApplicationPayment::find(auth('api')->user()->id)->update(['is_applied' => true]);
                return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Application Form Submitted Successfully.', 'data' => $application_form]);
            }
        } catch (\Exception $e) {
            // Consider logging the error here for debugging
            Log::error('Error uploading application form: ' . $e->getMessage());
            return response()->json(['status' => 500, 'response' => 'Server Error', 'message' => 'Error Uploading Application Form.'], 500);
        }
    }

    public function uploadPassport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'passport' => ['required', 'file'] // ensure these MIME types cover your needs
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Entity', 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('passport')) {
            $passportNameToStore = $this->uploadFile($request->file('passport'), 'LMS/passport');
            if ($passportNameToStore) {
                return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Image Uploaded.', 'image_url' => $passportNameToStore]);
            } else {
                return response()->json(['status' => 500, 'response' => 'Server Error', 'message' => 'Failed to upload passport.'], 500);
            }
        }

        // Handle case where no file is provided
        return response()->json(['status' => 400, 'response' => 'Bad Request', 'message' => 'No passport file provided.'], 400);
    }

    public function uploadFirstSittingResult(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_sitting_result' => ['required', 'file'] // ensure these MIME types cover your needs
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Entity', 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('first_sitting_result')) {
            $firstResultNameToStore = $this->uploadFile($request->file('first_sitting_result'), 'LMS/first_sitting_result');
            if ($firstResultNameToStore) {
                return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Image Uploaded.', 'image_url' => $firstResultNameToStore]);
            } else {
                return response()->json(['status' => 500, 'response' => 'Server Error', 'message' => 'Failed to upload firstResult.'], 500);
            }
        }

        // Handle case where no file is provided
        return response()->json(['status' => 400, 'response' => 'Bad Request', 'message' => 'No passport file provided.'], 400);
    }

    public function uploadSecondSittingResult(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'second_sitting_result' => ['required', 'file'] // ensure these MIME types cover your needs
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Entity', 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('second_sitting_result')) {
            $secondResultNameToStore = $this->uploadFile($request->file('second_sitting_result'), 'LMS/second_sitting_result');
            if ($secondResultNameToStore) {
                return response()->json(['status' => 200, 'response' => 'Successful', 'message' => 'Image Uploaded.', 'image_url' => $secondResultNameToStore]);
            } else {
                return response()->json(['status' => 500, 'response' => 'Server Error', 'message' => 'Failed to upload secondResult.'], 500);
            }
        }

        // Handle case where no file is provided
        return response()->json(['status' => 400, 'response' => 'Bad Request', 'message' => 'No passport file provided.'], 400);
    }

    private function uploadFile($file, $folder)
    {
        try {
            $uploadedFile = cloudinary()->upload($file->getRealPath(), [
                'folder' => $folder
            ]);
            return $uploadedFile->getSecurePath();
        } catch (\Exception $e) {
            // Consider logging the error here for debugging
            Log::error('Error uploading file to Cloudinary: ' . $e->getMessage());
            return null;
        }
    }

    public function initializeAcceptancePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()], 422);
        }

        $user = ApplicationPayment::find(auth('api')->user()->id);
        if ($user->admission_status != 'admitted') {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => 'User has not been admited'], 422);
        }

        $callback_url = env('FRONTEND_BASE_URL', 'https://odl-esut.qverselearning.org') . '/admission/payments/verify-acceptance';
        $serviceCode = '0013098YA2VG';
        $client = new Client();
        $response = $client->post('https://api.credocentral.com/transaction/initialize', [
            'headers' => [
                'Authorization' => "1PUB1309n0f51XpxIMIR0hvcEhH90u88HOl338",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'json' => [
                "customerFirstName" => $user->first_name,
                "customerLastName" => $user->last_name,
                "customerPhoneNumber" => $user->phone_number,
                "email" => $user->email,
                "amount" => $request->amount * 100,
                "callback_url" => $callback_url,
                "serviceCode" => $serviceCode,
                "metadata" => [
                    'user_id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'payment_type' => 'Acceptance Fee Payment',
                    'amount' => $request->amount,
                ]
            ],
        ]);
        $data = json_decode($response->getBody());
        return response()->json($data);
    }

    public function verifyAcceptancePayment()
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

            if ($status == 200) {
                $updateDetail = ApplicationPayment::where(['email' => $email])->update(['acceptance_fee_payment_status' => true]);
                PaymentLog::create([
                    'user_id' => auth('api')->user()->id,
                    'payment_type' => $this->getInsightTagValue('payment_type', $callback->data->metadata),
                    'amount' => $this->getInsightTagValue('amount', $callback->data->metadata),
                    'reference' => $reference,
                    'status' => 'Paid'
                ]);
                return response()->json([
                    'status' => 'Successful',
                    'message' => 'Acceptance Fee Payment was successful'
                ], 200);
            } else {
                return response()->json([
                    'status' => 'Failed',
                    'message' => 'Failed to confirm Payment'
                ], 401);
            }
        }
    }

    public function initializeTuitionPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'response' => 'Unprocessable Content',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = ApplicationPayment::find(auth('api')->user()->id);

        if (!$user) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'User payment record not found.',
            ], 404);
        }

        if ($user->acceptance_fee_payment_status == 0) {
            return response()->json([
                'status' => 422,
                'response' => 'Unprocessable Content',
                'message' => 'User needs to pay the acceptance fee first.'
            ], 422);
        }

        $fullTuitionFee = 195000;
        $minimumInstallment = $fullTuitionFee * 0.5; // 50% of the tuition fee
        $amount = $request->amount;
        $remainingBalance = $fullTuitionFee - $user->tuition_amount_paid;

        // Check for initial installment minimum
        if ($user->tuition_amount_paid == 0 && $amount < $minimumInstallment) {
            return response()->json([
                'status' => 422,
                'response' => 'Unprocessable Content',
                'message' => 'The initial installment must be at least 50% of the total tuition fee (' . number_format($minimumInstallment, 2) . ').',
            ], 422);
        }

        // Check if the second payment is less than the remainingBalance
        if ($user->tuition_amount_paid != 0 && $amount < $remainingBalance) {
            return response()->json([
                'status' => 422,
                'response' => 'Unprocessable Content',
                'message' => 'The second installment must complete the total tuition fee of (' . number_format($fullTuitionFee, 2) . ').',
                'data' => [
                    'remaining_balance' => $remainingBalance
                ]
            ], 422);
        }

        // Prevent overpayment
        if ($amount > $remainingBalance) {
            $amount = $remainingBalance;
        }

        $callback_url = env('FRONTEND_BASE_URL', 'https://odl-esut.qverselearning.org') . '/admission/payments/verify-tuition';
        $client = new Client();

        try {
            $response = $client->post('https://api.credocentral.com/transaction/initialize', [
                'headers' => [
                    'Authorization' => "1PUB1309n0f51XpxIMIR0hvcEhH90u88HOl338",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    "customerFirstName" => $user->first_name,
                    "customerLastName" => $user->last_name,
                    "customerPhoneNumber" => $user->phone_number,
                    "email" => $user->email,
                    "amount" => $amount * 100, // Convert amount to the smallest currency unit
                    "callback_url" => $callback_url,
                    "metadata" => [
                        'user_id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'payment_type' => 'Tuition Fee Payment',
                        'amount' => $amount,
                    ],
                ],
            ]);

            $data = json_decode($response->getBody());


            // 1. Generate user regNo
            // 2. Save to LMS


            // Return success response
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'response' => 'Internal Server Error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function profile(Request $request)
    {
        $user = ApplicationPayment::where('id', auth()->user()->id)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        return response()->json(['status' => 200, 'response' => $user->last_name . ' ' . $user->first_name . ` fetched successfully`, 'user' => $user], 200);
    }

    public function applicationData(Request $request)
    {
        // Fetch all payments with their associated application forms
        $application = ApplicationPayment::where(['id' => auth()->user()->id])->with('application')->first();

        // Check if the collection is empty
        if (!$application) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'No Application(s) found'
            ], 404);
        }

        // Return a successful response with the data
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            "message" => "Application Detail fetched successfully",
            "data" => $application
        ], 200);
    }

    public function verifyTuitionPayment()
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
            $amountPaid = $callback->data->transAmount;
            //              200 ?? 
            //  'faustinachigozie35@gmail.com' ?? 
            //  50000 ?? 

            if ($status == 200) {

                try {
                    do {
                        $reg_number = Carbon::now()->format('Y') . '345' . rand(1, 999);

                        // Retrieve the user's payment record
                        $userPayment = ApplicationPayment::where('reference', auth('api')->user()->reference)->first();

                        if (!$userPayment) {
                            return response()->json([
                                'status' => 'Error',
                                'message' => 'User payment record not found'
                            ], 404);
                        }

                        // Calculate the new tuition amount paid
                        $newTuitionAmountPaid = $userPayment->tuition_amount_paid + $amountPaid;

                        // Determine if the full tuition has been paid
                        $isTuitionFullyPaid = $newTuitionAmountPaid >= 195000;





                        // 2. Save to LMS
                        // OLD_LOCAL_TOKEN: 68f0662bb761de4ee215bf1d6c298ae8;
                        // SUPER_NEW_TOKEN: 84aa504c7078efdbbeb248c7d61e1b63;

                        // Get department Info
                        // $department = Department::find($userPayment->department_id);

                        $lms = Http::get(env('LMS_WS_SERVER') . '/webservice/rest/server.php', [
                            'wstoken' => env('LMS_WS_TOKEN'),
                            'wsfunction' => 'core_user_create_users',
                            'moodlewsrestformat' => 'json',
                            'users' => [
                                [
                                    'username' => $reg_number,
                                    'password' => 'P@55word', // must meet Moodle's password policy
                                    'firstname' => $userPayment->first_name,
                                    'lastname' => $userPayment->last_name,
                                    'email' =>  $userPayment->email,
                                    'phone1' =>  $userPayment->phone_number,
                                    'department' =>  $userPayment->level ?? null,
                                    'address' =>  $userPayment->address ?? null,
                                    'country' =>  'NG',
                                    'auth' => 'manual',
                                    'preferences' => [
                                        [
                                            'type' => 'auth_forcepasswordchange',
                                            'value' => 0
                                        ]
                                    ]
                                ]
                            ]
                        ]);
                        $lmsResult = $lms->json();
                        $lmsResultOk = $lmsResult[0]['id'] ?? false;

                        if ($lmsResultOk) {
                        }
                        $updateDetail = ApplicationPayment::where('reference', $userPayment->reference)
                            ->update([
                                'tuition_payment_status' => 1, // Store 'true' because user has paid part
                                'reg_number' => $reg_number, // Set reg_number once initial payment has been made
                                'tuition_amount_paid' => $newTuitionAmountPaid,
                            ]);
                        //  && !$lmsResultOk
                    } while (!$updateDetail); // Repeat if update fails
                } catch (QueryException $e) {
                    if ($e->errorInfo[1] == 1062) { // Handle duplicate entry error
                        return response()->json([
                            'status' => 'Failed',
                            'message' => 'Duplicate registration number, please try again'
                        ], 409);
                    }
                    return response()->json([
                        'status' => 'Failed',
                        'message' => 'Database error'
                    ], 500);
                }

                PaymentLog::create([
                    'user_id' => auth('api')->user()->id,
                    'payment_type' => $this->getInsightTagValue('payment_type', $callback->data->metadata),
                    'amount' => $this->getInsightTagValue('amount', $callback->data->metadata),
                    'reference' => $reference,
                    'status' => 'Paid'
                ]);
                return response()->json([
                    'status' => 'Successful',
                    'message' => 'Tuition Fee Payment was successful',
                    'data' => [
                        // 'tuition_payment_status' => $isTuitionFullyPaid,
                        'tuition_payment_status' => 1,
                        'tuition_amount_paid' => $newTuitionAmountPaid,
                        // 'reg_number' => $isTuitionFullyPaid ? $reg_number : null,
                        'reg_number' => $reg_number,
                    ],
                ], 200);
            } else {
                return response()->json([
                    'status' => 'Failed',
                    'message' => 'Failed to confirm Payment'
                ], 401);
            }
        }
    }

    public function scholarshipRegistration(Request $request)
    {
        $reference = $request->reference;

        if (!$reference) {
            return response()->json([
                'status' => 'Request Failed',
                'message' => 'No Reference Provided'
            ], 422);
        }

        try {
            do {
                // Generate a unique registration number
                $reg_number = Carbon::now()->format('Y') . '345' . rand(1, 999);

                // Retrieve the user's payment record
                $userPayment = ApplicationPayment::where('reference', auth('api')->user()->reference)->first();

                if (!$userPayment) {
                    return response()->json([
                        'status' => 'Error',
                        'message' => 'User payment record not found'
                    ], 404);
                }

                $amountPaid = 0;
                // Calculate the new tuition amount paid
                $newTuitionAmountPaid = $userPayment->tuition_amount_paid + $amountPaid;

                // Determine if the full tuition has been paid
                $isTuitionFullyPaid = $newTuitionAmountPaid >= 195000;

                // Attempt to update the record
                $updateDetail = ApplicationPayment::where('reference', $userPayment->reference)
                    ->update([
                        // 'tuition_payment_status' => $isTuitionFullyPaid,
                        'tuition_payment_status' => 1, // Store 'true' because user has paid part
                        // 'reg_number' => $isTuitionFullyPaid ? $reg_number : $userPayment->reg_number, // Set reg_number only when fully paid
                        'reg_number' => $reg_number, // Set reg_number once initial payment has been made
                        'tuition_amount_paid' => $newTuitionAmountPaid,
                    ]);
            } while (!$updateDetail); // Repeat if update fails
        } catch (QueryException $e) {
            if ($e->errorInfo[1] == 1062) { // Handle duplicate entry error
                return response()->json([
                    'status' => 'Failed',
                    'message' => 'Duplicate registration number, please try again'
                ], 409);
            }
            return response()->json([
                'status' => 'Failed',
                'message' => 'Database error'
            ], 500);
        }

        PaymentLog::create([
            'user_id' => auth('api')->user()->id,
            'payment_type' => $this->getInsightTagValue('payment_type', $callback->data->metadata),
            'amount' => $this->getInsightTagValue('amount', $callback->data->metadata),
            'reference' => $reference,
            'status' => 'Paid'
        ]);
        return response()->json([
            'status' => 'Successful',
            'message' => 'Tuition Fee Payment was successful',
            'data' => [
                // 'tuition_payment_status' => $isTuitionFullyPaid,
                'tuition_payment_status' => 1,
                'tuition_amount_paid' => $newTuitionAmountPaid,
                // 'reg_number' => $isTuitionFullyPaid ? $reg_number : null,
                'reg_number' => $reg_number,
            ],
        ], 200);
    }

    private function getInsightTagValue($tag, $data)
    {
        // Loop through the data array
        for ($i = 0; $i < count($data); $i++) {
            // Check if the current item's insightTag matches the given tag
            if ($data[$i]->insightTag === $tag) {
                // Return the insightTagValue if a match is found
                return $data[$i]->insightTagValue;
            }
        }

        // Return null if no match is found
        return null;
    }

    public function testMail(Request $request)
    {

        $_uid = $request->user_id ?? 1;
        // accepts send_to_user == true|false    (Optional param for sending email to user)

        $user = ApplicationPayment::findOrFail($_uid);


        $currentYear = Carbon::now()->format('Y');
        $previousYear = Carbon::now()->subYear()->format('Y');
        $academic_year = $previousYear . '-' . $currentYear;
        $deadline_date = Carbon::now()->addWeeks(2)->format('m-d-Y');

        // Prepare admission mail data
        $faculty = Faculty::find(6);
        $department = Department::find(17);
        $mailData = [
            'name' => $user->first_name . ' ' . $user->last_name,
            'faculty' => $faculty->faculty_name,
            'department' => $department->department_name,
            'school_name' => 'ESUT ODL',
            'academic_year' => $academic_year,
            'deadline_date' => $deadline_date,
            'portal_link' => env('FRONTEND_BASE_URL', 'https://odl-esut.qverselearning.org') . '/admissions',
            'contact_info' => 'contact@odl-esut.qverselearning.org',
            'sender_name' => 'ESUT ODL Admission Officer',
            'sender_position' => 'Admissions Office',
            //'date' => Carbon::now(),
        ];

        $pdfService = new PDFService();
        $pdfPath = $pdfService->generatePDF($mailData);

        // Send admission email
        $sendToUser = $request->send_to_user ?? false;
        $emailUser = ($sendToUser) ? $user->email : 'valentinechimefo@gmail.com';

        Mail::to($emailUser)->send(new AdmissionMail($mailData, $pdfPath));


        return response()->json(['message' => 'Admission approved and email sent successfully!']);
    }



    public function changePassword(Request $request)
    {
        // Validate the request
        $this->validate($request, [
            'current_password' => 'required',
            'new_password' => 'required',
        ]);

        $user = Auth::user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();
        return response()->json([
            'status' => true,
            'message' => 'Password successfully changed'
        ]);
    }



    public function truncate(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'admin_email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], 422);
        }

        if (!User::checkAdminAuthority()) {
            return response()->json(['status' => 401, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 401);
        }

        $user = ApplicationPayment::where('email', $request->admin_email)->first();
        if ($user && $user->role == 'ADMIN') {
            DB::table('application_payments')->truncate();
            return response()->json([
                'status' => true,
                'message' => 'Application_payments table erased!'
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Your email/role is incorrect'
        ]);
    }
}
