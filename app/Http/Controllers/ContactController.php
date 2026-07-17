<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $active = $request->string('active')->trim()->toString();
        $role = $request->string('role')->trim()->toString();

        $query = Contact::query()
            ->with(['user', 'servers'])
            ->latest();

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($active !== '') {
            $query->where('active', $active === '1');
        }

        if ($role !== '') {
            $query->where('role', $role);
        }

        $contacts = $query->paginate(10)->withQueryString();

        return view('contacts.index', [
            'contacts' => $contacts,
            'totalContacts' => Contact::count(),
            'activeContacts' => Contact::where('active', true)->count(),
            'search' => $request->string('q')->toString(),
            'selectedActive' => $active,
            'selectedRole' => $role,
        ]);
    }

    public function create(): View
    {
        return view('contacts.create', [
            'contact' => new Contact(),
            'users' => User::query()->orderBy('name')->get(),
            'servers' => Server::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['active'] = $request->boolean('active', true);
        if (!isset($data['allowed_since'])) {
            $data['allowed_since'] = now();
        }

        $contact = Contact::create($data);
        $contact->servers()->sync($request->validated('servers', []));

        return redirect()
            ->route('contacts.show', $contact)
            ->with('status', 'Contacto creado correctamente.');
    }

    public function show(Contact $contact): View
    {
        $contact->load(['user', 'servers']);

        return view('contacts.show', [
            'contact' => $contact,
        ]);
    }

    public function edit(Contact $contact): View
    {
        return view('contacts.edit', [
            'contact' => $contact->load(['user', 'servers']),
            'users' => User::query()->orderBy('name')->get(),
            'servers' => Server::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $data = $request->validated();
        $data['active'] = $request->boolean('active', false);

        $contact->fill($data);
        $contact->save();
        $contact->servers()->sync($request->validated('servers', []));

        return redirect()
            ->route('contacts.show', $contact)
            ->with('status', 'Contacto actualizado correctamente.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()
            ->route('contacts.index')
            ->with('status', 'Contacto eliminado lógicamente.');
    }
}
