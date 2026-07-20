<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::active()
            ->with('user')
            ->paginate(20);

        return response()->json($posts);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return 'posts.create';
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // validation
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_draft' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        // Save the data (Use the currently logged-in user)

        $post = Post::create([
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_draft' => $validated['is_draft'] ?? false,
            'published_at' => $validated['published_at'] ?? now(),
        ]);

        return response()->json($post, 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Retrive only active post
        $post = Post::with('user')
            ->active()
            ->findOrFail($id);

        return response()->json(
            $post->load('user')
        );

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Post $post)
    {
        abort_if(
            $post->user_id !== auth()->id(),
            403
        );

        return 'posts.edit';
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post)
    {
        // Only the post author can update the post
        abort_if(
            $post->user_id !== auth()->id(),
            403
        );

        // Check if the request method is PATCH (true = PATCH, false = PUT)
        $isPatch = $request->isMethod('patch');

        // Validation request data
        $validated = $request->validate([
            'title' => [$isPatch ? 'sometimes' : 'required', 'string', 'max:255'],
            'content' => [$isPatch ? 'sometimes' : 'required', 'string'],
            'is_draft' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ]);

        // Update the post
        $post->update($validated);

        // Return a suceessfull JSON response
        return response()->json($post->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        // Only the post author can delete the post
        abort_if(
            $post->user_id !== auth()->id(),
            403
        );
        // Delete the post
        $post->delete();

        // Return a suceessfull JSON response
        return response()->noContent();
    }
}
