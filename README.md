# picoPHP - Your Lightweight and Flexible PHP Framework

picoPHP is a minimalistic yet powerful PHP framework designed for rapid development and ease of use. It leverages file-based routing, dependency injection, and middleware to provide a clean and organized structure for your web applications.

## Key Features

*   **File-Based Routing:**  Define your routes simply by creating `routes.php` files within your application's directory structure.
*   **Dependency Injection:**  Automatically inject services (like Request, Response, Env, DB) into your route handlers for easy access and testing.
*   **Path Parameters:**  Easily capture dynamic path segments as parameters within your routes.
*   **Middleware:**  Implement reusable logic that can be applied before and after route handlers, allowing you to easily manage authentication, authorization, data validation, and more.
*   **HTTP Method Handling:** Easily define handlers for different HTTP methods (GET, POST, PUT, DELETE, etc.)

## Getting Started

1.  **Installation:**  Use Composer to install picoPHP.

    ```bash
    composer create-project adil-jaafar/picophp-template your-app-name
    ```

2.  **Directory Structure:**  Organize your application code within the `app` directory.

    ```
    app/
    ├── foo/
    │   ├── bar/
    │   │   └── routes.php   # Defines routes for /foo/bar
    │   └── routes.php       # Defines routes for /foo
    └── routes.php           # Defines routes for the root (/)
    ```

3.  **Routing with `routes.php`:** Create `routes.php` files to define your routes.

    ```php
    <?php

    // app/foo/bar/routes.php

    $get = function(Request $request, Response $response) {
        $data = ['message' => 'Hello from /foo/bar'];
        return $response(200)->json($data);
    };

    $post = function(Request $request, Response $response) {
        $body = $request->body();
        // Process the POST data
        return $response(201)->json(['message' => 'Resource created', 'data' => $body]);
    };
    ```

    This example defines a `GET` and `POST` route for `/foo/bar`.

4.  **Accessing Services:**  picoPHP automatically injects the required services into your route handlers.

    ```php
    <?php

    // app/routes.php

    $get = function(Request $request, Response $response, Env $env, DB $db) {
        $appName = $env('APP_NAME', 'My App');  // Access environment variables
        $users = $db->fetchAll('SELECT * FROM users'); // Access the database
        return $response(200)->json(['app_name' => $appName, 'users' => $users]);
    };
    ```

5.  **Path Parameters:** Use `Path` service to access route parameters defined with square brackets in the directory structure.

    ```php
    <?php

    // app/users/[id]/edit/routes.php
    $get = function(Path $path, Response $response) {
        $user_id = $path['id'];  // Access the "id" parameter from the URL

        // Fetch user data based on $user_id
        // ...

        return $response(200)->json(['user_id' => $user_id, /* ... */]);
    };


    // app/photo/[...chemin]/routes.php
    $get = function(Path $path, Response $response) {
        $photo_path = $path['chemin'];  // Access the "chemin" parameter from the URL which can contain multiple segments
        // Ex: /photo/path/to/my/image.jpg
        // $photo_path will be equal to "path/to/my/image.jpg"

        // Serve the image at $photo_path
        // ...

        return $response(200); // Or any other response
    };
    ```

    *   `/users/123/edit` will map to the function in `app/users/[id]/edit/routes.php` and the parameter 'id' will equal `123`.
    *   `/photo/path/to/my/image.jpg` will map to the function in `app/photo/[...chemin]/routes.php` and the parameter 'chemin' will equal `path/to/my/image.jpg`.

6. **Directory Naming Convention**
 Folders enclosed in parentheses `()` are intended for structural organization, e.g., `app/users/(admin)/edit/[user_id]`. They are used to map paths like `/users/edit/123` to the route structure, where `user_id` captures the dynamic parameter.

7.  **Middleware:** Add middleware to `middleware.php` files in any directory within your application. Middleware allows you to execute code before and after route handlers.

    ```php
    <?php

    // app/middleware.php

    $before = [
        function(Request $request, Response $response, Env $env) {
            // Check authentication before allowing access
            $apiKey = $request->header('X-API-Key');
            if ($apiKey !== $env('API_KEY')) {
                return $response(401)->json(['error' => 'Unauthorized']);
            }
            return true; // Continue to the route handler (if this returns nothing or is ommited)
        }
    ];

    // app/foo/middleware.php
    $before = [
        function(Request $request, Response $response) {
            // Log something
             error_log('Before foo');
             return true;
        }
    ];

    $after = [
        function(Request $request, Response $response) {
            $response->header('X-Powered-By', 'PicoPHP');
            return true;
        }
    ];
    ```

    *   `$before`:  An array of functions to execute before the route handler.
        *   If any `$before` middleware function returns a value (other than `true` or `null`), that value is considered the response, and the route handler is *not* executed.
        *   Returning `true` (or nothing at all) from a `$before` middleware function allows the request to proceed to the route handler (and to other `$before` middleware further down in the application's folder structure)
        *   If a `$before` middleware returns a `Response` object, processing stops, and the framework immediately returns this response. This is ideal for things like auth checks.
    *   `$after`: An array of functions to execute after the route handler. `$after` middleware run in the reverse order from the `$before` middleware.

**Middleware Execution Order:**

1.  `$before` middleware functions are executed from the root directory down to the directory containing the `routes.php` file. If a `before` returns a value other than true, processing stops.
2.  The route handler function in `routes.php` is executed.
3.  `$after` middleware functions are executed from the directory containing the `routes.php` file up to the root directory.

## Example:  Authentication Middleware

Create a `middleware.php` file in your `app` directory to implement global authentication.

```php
<?php

// app/middleware.php

$before = [
    function(Request $request, Response $response) {
        if (!isAuthenticated($request)) {
            return $response(401)->json(['error' => 'Unauthorized']);
        }
    }
];

function isAuthenticated(Request $request): bool {
  //TODO replace by a real auth check
  $token = $request->header("authorization");
  return $token === "Bearer mysecrettoken";
}