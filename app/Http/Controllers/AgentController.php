<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AgentController extends Controller
{
    public function store(Request $request) {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only Root Admins can forge new Agents.'], 403);
        }

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $agent = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'agent',
        ]);

        return response()->json([
            'message' => 'Agent access forged successfully.',
            'agent' => [
                'name' => $agent->name,
                'email' => $agent->email,
            ]
        ], 201);
    }
    public function index(Request $request) {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only Root Admins can view Agents.'], 403);
        }
        $agents = User::where('role', 'agent')->select('id', 'name', 'email', 'created_at')->orderBy('created_at', 'desc')->get();
        return response()->json($agents, 200);
    }

    public function update(Request $request, $id) {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only Root Admins can modify Agents.'], 403);
        }
        $agent = User::where('id', $id)->where('role', 'agent')->firstOrFail();

        $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|string|email|unique:users,email,'.$id,
            'password' => 'nullable|string|min:6',
        ]);

        if ($request->has('name')) $agent->name = $request->name;
        if ($request->has('email')) $agent->email = $request->email;
        if ($request->filled('password')) $agent->password = Hash::make($request->password);
        
        $agent->save();

        return response()->json([
            'message' => 'Agent clearance modified successfully.',
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
            ]
        ], 200);
    }

    public function destroy(Request $request, $id) {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only Root Admins can delete Agents.'], 403);
        }
        $agent = User::where('id', $id)->where('role', 'agent')->firstOrFail();
        $agent->delete();
        return response()->json(['message' => 'Agent access completely revoked and shredded.'], 200);
    }
}
