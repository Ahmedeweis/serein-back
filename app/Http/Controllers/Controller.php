<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Serein Store API Documentation",
    description: "Secure E-Commerce API Endpoints",
    contact: new OA\Contact(email: "admin@serein.com")
)]
#[OA\Server(
    url: "http://127.0.0.1:8000",
    description: "Local Development Server"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Enter token obtained from /api/auth/login"
)]
abstract class Controller
{
    //
}
