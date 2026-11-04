---
view: components.packages.quick-look
title: A quick look
subtitle: A user follows a post, and the post page shows its count.
file: routes/web.php
language: php
code: |
  use App\Post;

  Route::post('posts/{post}/follow', function (Post $post) {
      $created = request()->user()->follow($post);

      return response()->json([
          'created' => $created,                      // false when it was already followed
          'followers' => $post->followers()->count(),
      ]);
  })->middleware('auth');

  Route::get('posts', function () {
      return Post::withCount('followers')->latest()->paginate();
  });
---
