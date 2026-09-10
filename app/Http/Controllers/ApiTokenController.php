<?php

namespace App\Http\Controllers;

use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $token = $request->user()->createToken($request->name);

        $this->auditLogger->log($request->user(), 'api_token.created', "Membuat API token \"{$request->name}\"");

        return Redirect::route('profile.edit')->with('plainTextToken', $token->plainTextToken);
    }

    public function destroy(Request $request, PersonalAccessToken $token): RedirectResponse
    {
        abort_unless(
            $token->tokenable_type === get_class($request->user()) && $token->tokenable_id === $request->user()->id,
            403
        );

        $this->auditLogger->log($request->user(), 'api_token.revoked', "Mencabut API token \"{$token->name}\"");

        $token->delete();

        return Redirect::route('profile.edit')->with('success', 'API token dicabut.');
    }
}
