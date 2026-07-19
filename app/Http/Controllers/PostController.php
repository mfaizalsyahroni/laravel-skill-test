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
        $posts = Post::with('user')
            ->where('is_draft', false)
            ->where('published_at', '<=', now())
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
            'is_draft' => ['boolean'],
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

        return response()->json([
            'message' => 'Post created successfully',
            'post' => $post,
        ], 201);

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

        return response()->json($post);

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Post $post)
    {
        // dd('edit');
        // Only the post edit author can access this route
        abort_if(auth()->id() !== $post->user_id, 403);

        return 'posts.edit';
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post)
    {
        // Only the post author can update the post
        abort_if(auth()->id() !== $post->user_id, 403); // 403(rejected)

        // Validation request data
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_draft' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        // Update the post
        $post->update($validated);

        // Return a suceessfull JSON response
        return response()->json([
            'message' => 'Post Updated Successfully',
            'post' => $post,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        // Only the post author can delete the post
        abort_if(auth()->id() !== $post->user_id, 403); // 403(rejected)

        // Delete the post
        $post->delete();

        // Return a suceessfull JSON response
        return response()->json([
            'message' => 'Post Deleted Successfully',
        ], 204);
    }
}
