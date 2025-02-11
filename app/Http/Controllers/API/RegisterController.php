<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\SmsController;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\UpdatePasswordValidate;
use App\Mail\OtpMail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use OpenApi\Annotations as OA;

class RegisterController extends BaseController
{
    protected $smsController;
    public function __construct(SmsController $smsController)
    {
        $this->smsController = $smsController;
    }
    /**
     * @OA\Post(
     *     path="/api/register",
     *     tags={"Authentication"},
     *     summary="Register a new user",
     *     operationId="registerUser",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","phone_number","user_type"},
     *             @OA\Property(property="name", type="string", format="name", example="John Doe", description="User's name"),
     *             @OA\Property(property="phone_number", type="string", format="phone_number", example="08012345678", description="User's phone number"),
     *             @OA\Property(property="user_type", type="string", format="user_type", example="customer", description="User's type"),
     *             @OA\Property(property="email", type="string", format="email", example="test@gmail.com"),
     *         ),
     *     ),
     * @OA\Response(
     *           response=201,
     *           description="Registered Successfully",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="User registered successfully"),
     *               @OA\Property(property="token", type="string", example="abc123"),
     *           )
     *        ),
     * @OA\Response(
     *           response=422,
     *           description="Unprocessable Entity",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="Validation error"),
     *           )
     *        ),
     * @OA\Response(response=400, description="Bad request"),
     * @OA\Response(response=404, description="Resource Not Found"),
     * )
     */

    public function register(RegisterUserRequest $request)
    {

        $user = User::where('phone_number', $request->input('phone_number'))->first();

        if ($user) {
            return response()->json(['message' => 'User already exists'], 422);
        }

         $otp = 111111;//rand(100000, 999999);
        $email = $request->input('email');
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $email,
            'phone_number' => $request->phone_number,
            'otp' => Hash::make($otp),
            'otp_attempts' => 0,
            'otp_last_attempt_at' => now(),
            'user_type' => $request->input('user_type'),
        ]);
        //make a call to the sms controller to send the otp

      //  $this->smsController->sendOtp($request->input('phone_number'), $otp);


       // Mail::to($email)->send(new OtpMail($otp));
        return response()->json(['message' => 'Please verify your phone number']);
    }
     // api documentation

    /**
     * @OA\Post(
     *     path="/api/verify-otp",
     *     tags={"Authentication"},
     *     summary="Verify OTP",
     *     operationId="verifyOtp",
     *     @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *     required={"phone_number","otp"},
     *     @OA\Property(property="phone_number", type="string", format="phone_number", example="08012345678", description="User's phone number"),
     *     @OA\Property(property="otp", type="string", format="otp", example="1111", description="OTP sent to the user's phone number"),
     *
     *     ),
     *      ),
     *     @OA\Response(
     *      response=200,
     *     description="OTP verified successfully",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="OTP verified successfully"),
     *     @OA\Property(property="token", type="string", example="abc123"),
     *     )
     *     ),
     *     @OA\Response(
     *     response=422,
     *     description="Unprocessable Entity",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Invalid OTP"),
     *     )
     *    ),
     *     @OA\Response(response=400, description="Bad request"),
     *
     *     )
     *
     */
    public function verifyOtp(Request $request)
    {
        // Validate request data including phone_number and otp

        // Retrieve user by phone number
        $user = User::where('phone_number', $request->input('phone_number'))->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }


        // Verify OTP
        if (!Hash::check($request->input('otp'), $user->otp)) {
            return response()->json(['message' => 'Invalid OTP'], 422);
        }

        // Reset OTP attempts and cooldown on successful verification
        $user->update([
            'otp_attempts' => 0,
            'otp_last_attempt_at' => null,
            'password' => Hash::make($request->input('password')),
        ]);

        // Mark user as verified (you may update the 'verified' field in the database)
        $user->update(['otp' => null]);

        return response()->json(['message' => 'OTP verified successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/resend-otp",
     *     tags={"Authentication"},
     *     summary="Resend OTP",
     *     operationId="resendOtp",
     *     @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *     required={"phone_number"},
     *     @OA\Property(property="phone_number", type="string", format="phone_number", example="08012345678", description="User's phone number"),
     *     ),
     *      ),
     *     @OA\Response(
     *      response=200,
     *     description="OTP resent successfully",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="OTP resent successfully"),
     *     )
     *     ),
     *     @OA\Response(
     *     response=422,
     *     description="Unprocessable Entity",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Resend not allowed at this time"),
     *     )
     *    ),
     *     @OA\Response(response=400, description="Bad request"),
     *
     *     )
     *
     */
    public function resendOtp(Request $request)
    {
        // Validate request data including phone_number

        // Retrieve user by phone number
        $user = User::where('phone_number', $request->input('phone_number'))->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if the user has exceeded the maximum OTP attempts or the cooldown period
        if ($this->hasExceededOtpAttempts($user)) {
            return response()->json(['message' => 'Resend not allowed at this time'], 422);
        }

        // Generate new OTP
        $otp = 111111; //rand(1000, 9999);

        // Update user with hashed OTP and reset attempts and cooldown
        $user->update([
            'otp' => Hash::make($otp),
            'otp_attempts' => $user->otp_attempts + 1,
            'otp_last_attempt_at' => now(),
        ]);

       // $this->smsController->sendOtp($request->input('phone_number'), $otp);
        return response()->json(['message' => 'OTP resent successfully']);
    }

    protected function hasExceededOtpAttempts($user)
    {
        return $user->otp_attempts >= 3;
    }

    protected function hasExceededCooldown($user)
    {
        // Check if cooldown period (e.g., 5 minutes) has passed since the last OTP attempt
        return $user->otp_last_attempt_at && Carbon::parse($user->otp_last_attempt_at)->addMinutes(5)->isPast();
    }
    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Authentication"},
     *     summary="Login a user",
     *     operationId="loginUser",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone_number","password"},
     *             @OA\Property(property="phone_number", type="string", format="phone_number", example="08012345678", description="User's phone number"),
     *             @OA\Property(property="password", type="string", format="password", example="password", description="User's password"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="abc123"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     security={{"Bearer":{}}}
     * )
     */

    public function login(Request $request)
    {

        $rules = [
            'phone_number' => 'required|string',
            'password' => 'required|string',
        ];

        // Custom error messages
        $messages = [
            'name.required' => 'The name field is required.',
            // Add more custom messages as needed
        ];

        // Validate the request data
        $validator = Validator::make($request->all(), $rules, $messages);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $credentials = [
            'phone_number' => $request->input('phone_number'),
            'password' => $request->input('password'),
        ];

        if (Auth::attempt($credentials)) {
            // Authentication passed
            $user = Auth::user();
            $token = $this->createToken($user);

            return response()->json(['token' => $token, 'user' => $user]);
        } else {
            // Authentication failed
            return response()->json(['message' => 'Invalid credentials'], 422);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/update-password",
     *     tags={"Authentication"},
     *     summary="Create Password",
     *     operationId="createPassword",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone_number","password"},
     *           @OA\Property(property="phone_number", type="string", format="phone_number", example="08012345678", description="User's phone number"),
     *          @OA\Property(property="password", type="string", format="password", example="password", description="User's password"),
     *         ),
     *     ),
     *    @OA\Response(
     *     response=200,
     *     description="Password updated successfully",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Password updated successfully"),
     *     @OA\Property(property="token", type="string", example="abc123"),
     *     )
     *    ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request")
     * )
     */

    public function updateUserPassword(UpdatePasswordValidate $request)
    {
        $user = User::where('phone_number', $request->input('phone_number'))->first();
        if(!$user){
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();
        return response()->json(['message' => 'Password updated successfully', 'token' => $this->createToken($user)]);
    }

    //create token function
    public function createToken($user)
    {

        return $user->createToken('Personal Access Token')->accessToken;
    }



}
