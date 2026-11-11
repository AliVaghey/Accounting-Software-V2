<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(Request $request, Contact $contact): View
    {
        abort_unless($contact->user_id === $request->user()?->id, 404);

        return view('contacts.show');
    }
}
