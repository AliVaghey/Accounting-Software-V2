<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactPhotoController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Contact $contact): StreamedResponse
    {
        abort_unless($contact->user_id === $request->user()?->id, 404);
        abort_if($contact->profile_photo === null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($contact->profile_photo), 404);

        return $disk->response($contact->profile_photo, headers: [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
