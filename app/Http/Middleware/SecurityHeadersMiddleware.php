<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS requests for CORS
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }
        
        // Add CORS headers for frontend communication
        $allowedOrigins = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://localhost:3000',
            'http://127.0.0.1:3000'
        ];
        
        $origin = $request->header('Origin');
        if (in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }
        
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');
        
        // Add security headers to prevent common attacks
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN'); // Changed from DENY to allow same origin
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        
        // Add HSTS for HTTPS (only in production)
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy (adjusted for development)
        if (app()->environment('local')) {
            $response->headers->set('Content-Security-Policy', 
                "default-src 'self' 'unsafe-inline' 'unsafe-eval' localhost:* 127.0.0.1:*; " .
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' localhost:* 127.0.0.1:*; " .
                "style-src 'self' 'unsafe-inline' localhost:* 127.0.0.1:*; " .
                "img-src 'self' data: https: localhost:* 127.0.0.1:*; " .
                "connect-src 'self' https: localhost:* 127.0.0.1:* ws: wss:; " .
                "font-src 'self' localhost:* 127.0.0.1:*; " .
                "object-src 'none'; " .
                "media-src 'self' localhost:* 127.0.0.1:*;"
            );
        } else {
            $response->headers->set('Content-Security-Policy', 
                "default-src 'self'; " .
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
                "style-src 'self' 'unsafe-inline'; " .
                "img-src 'self' data: https:; " .
                "connect-src 'self' https:; " .
                "font-src 'self'; " .
                "object-src 'none'; " .
                "media-src 'self'; " .
                "frame-src 'none';"
            );
        }
        
        return $response;
    }
}












