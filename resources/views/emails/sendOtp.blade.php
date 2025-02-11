<!DOCTYPE html>
<html>
<head>
    <title>OTP Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .container {
            padding: 20px;
            max-width: 600px;
            margin: auto;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding: 10px 0;
            background-color: #4CAF50;
            color: white;
            border-radius: 8px 8px 0 0;
        }
        .content {
            padding: 20px;
        }
        .otp {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            text-align: center;
        }
        .footer {
            padding: 10px 0;
            text-align: center;
            color: #aaa;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>Verification Code</h2>
    </div>
    <div class="content">
        <p>Hello,</p>
        <p>Please use the following verification code to complete your action:</p>
        <p class="otp">{{ $otp }}</p>
        <p>This code will expire in 10 minutes.</p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} AppFarm. All rights reserved.</p>
    </div>
</div>
</body>
</html>

