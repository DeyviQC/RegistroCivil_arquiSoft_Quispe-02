<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PerfilController extends Controller
{
    public function edit(Request $request)
    {
        return view('perfil.edit', ['usuario' => $request->user()]);
    }

    public function update(Request $request)
    {
        $perfilCompleto = $request->routeIs('perfil.guardar');
        $reglas = ['foto' => ($perfilCompleto ? 'nullable' : 'required').'|image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=6000,max_height=6000'];
        if ($perfilCompleto) {
            $reglas += [
                'name' => ['required', 'string', 'max:150'],
                'quitar_foto' => ['sometimes', 'boolean'],
            ];
        }
        if ($perfilCompleto && $request->user()->isAdmin()) {
            $reglas += [
                'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($request->user()->id)],
                'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($request->user()->id)],
                'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()],
                'current_password' => $request->filled('password') ? ['required', 'current_password'] : ['nullable'],
            ];
        }
        $datos = $request->validate($reglas, [
            'foto.required' => 'Seleccione una foto.',
            'foto.image' => 'Seleccione una imagen válida.',
            'foto.mimes' => 'La foto debe ser JPG, PNG o WEBP.',
            'foto.max' => 'La foto debe pesar como máximo 2 MB.',
            'foto.dimensions' => 'La foto no debe superar los 6000 píxeles de ancho o alto.',
        ]);
        $usuario = $request->user();
        $anterior = $usuario->foto;
        $ruta = null;
        if ($request->hasFile('foto')) {
            $ruta = $request->file('foto')->store('private/perfiles/'.$usuario->id, 'local');
            abort_unless($ruta, 500, 'No se pudo guardar la foto.');
        }
        try {
            if ($perfilCompleto) {
                $usuario->fill(collect($datos)->only(['name', 'username', 'email'])->all());
                if (!empty($datos['password'])) { $usuario->password = $datos['password']; }
            }
            if ($ruta || $request->boolean('quitar_foto')) { $usuario->foto = $ruta; }
            $usuario->save();
        } catch (\Throwable $e) {
            if ($ruta) { Storage::disk('local')->delete($ruta); }
            throw $e;
        }
        if ($anterior && $anterior !== $usuario->foto) { Storage::disk('local')->delete($anterior); }
        return redirect()->route('perfil.edit')->with('exito', 'Su perfil se actualizó correctamente.');
    }

    public function foto(Request $request)
    {
        $ruta = $request->user()->foto;
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);
        return response()->file(Storage::disk('local')->path($ruta), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request)
    {
        $usuario = $request->user();
        $anterior = $usuario->foto;
        $usuario->foto = null;
        $usuario->save();
        if ($anterior) { Storage::disk('local')->delete($anterior); }
        return redirect()->route('perfil.edit')->with('exito', 'Foto de perfil eliminada.');
    }
}
