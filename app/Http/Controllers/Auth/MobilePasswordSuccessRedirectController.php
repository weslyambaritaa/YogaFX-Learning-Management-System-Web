<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MobilePasswordSuccessRedirectController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return response(<<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Updated</title>
</head>
<body style="font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a;">
    <main style="max-width: 32rem; margin: 0 auto; min-height: 100vh; display: flex; align-items: center; padding: 2rem;">
        <section style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 2rem; width: 100%; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);">
            <h1 style="margin-top: 0; font-size: 1.5rem;">Password updated successfully</h1>
            <p style="line-height: 1.6; color: #475569;">
                Your password has been changed successfully for the YogaFX mobile flow.
            </p>
            <p style="line-height: 1.6; color: #475569; margin-top: 1rem;">
                Please return to the YogaFX mobile app manually and continue from the app login screen using your new password.
            </p>
            <div style="margin-top: 1.5rem; padding: 1rem 1.25rem; border-radius: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; color: #334155;">
                Manual next step:
                <strong>Go back to the mobile app, then sign in again with your new password.</strong>
            </div>
        </section>
    </main>
</body>
</html>
HTML, 200);
    }
}
