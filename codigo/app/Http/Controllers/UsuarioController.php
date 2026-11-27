<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoriaService)
    {
    }

    public function index()
    {
        $this->authorize('viewAny', User::class);

        $usuarios = User::with('roles')->orderBy('name')->paginate(15);

        return view('usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $this->authorize('create', User::class);

        $roles = Role::all();

        return view('usuarios.create', compact('roles'));
    }

    public function store(StoreUsuarioRequest $request)
    {
        $this->authorize('create', User::class);

        $datos = $request->validated();

        $usuario = User::create([
            'name' => $datos['name'],
            'username' => $datos['username'],
            'email' => $datos['email'],
            'password' => Hash::make($datos['password']),
            'is_active' => true,
        ]);

        $rol = Role::where('slug', $datos['role'])->firstOrFail();
        $usuario->roles()->sync([$rol->id]);

        $this->auditoriaService->registrar(
            AuditoriaService::CREAR_USUARIO,
            'Usuarios',
            $usuario->id,
            "Usuario '{$usuario->username}' creado con rol {$rol->nombre}."
        );

        return redirect()->route('usuarios.index')->with('exito', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        $this->authorize('update', $usuario);

        $roles = Role::all();

        return view('usuarios.edit', compact('usuario', 'roles'));
    }

    public function update(UpdateUsuarioRequest $request, User $usuario)
    {
        $this->authorize('update', $usuario);

        $datos = $request->validated();

        $usuario->name = $datos['name'];
        $usuario->username = $datos['username'];
        $usuario->email = $datos['email'];
        $usuario->is_active = $datos['is_active'];

        if (! empty($datos['password'])) {
            $usuario->password = Hash::make($datos['password']);
        }

        $usuario->save();

        $rol = Role::where('slug', $datos['role'])->firstOrFail();
        $usuario->roles()->sync([$rol->id]);

        $this->auditoriaService->registrar(
            AuditoriaService::EDITAR_USUARIO,
            'Usuarios',
            $usuario->id,
            "Usuario '{$usuario->username}' actualizado."
        );

        return redirect()->route('usuarios.index')->with('exito', 'Usuario actualizado correctamente.');
    }

    /**
     * Activar/desactivar (no se permite eliminar usuarios, Sección 24).
     */
    public function alternarEstado(User $usuario)
    {
        $this->authorize('update', $usuario);

        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puede desactivar su propio usuario.');
        }

        $usuario->is_active = ! $usuario->is_active;
        $usuario->save();

        $this->auditoriaService->registrar(
            AuditoriaService::DESACTIVAR_USUARIO,
            'Usuarios',
            $usuario->id,
            "Usuario '{$usuario->username}' " . ($usuario->is_active ? 'activado' : 'desactivado') . '.'
        );

        return back()->with('exito', 'Estado del usuario actualizado.');
    }

    public function foto(User $usuario)
    {
        $this->authorize('view', $usuario);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($usuario->foto && $disk->exists($usuario->foto), 404);
        return response()->file($disk->path($usuario->foto), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
