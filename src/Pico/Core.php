<?php

namespace PicoPHP;

use PicoPHP\Services\Env;
use PicoPHP\Services\Path;
use PicoPHP\Services\Request;
use PicoPHP\Services\Response;
use ReflectionFunction;

class Core
{
    protected string $routesDirectory;

    public function __construct($congiguration = [])
    {
        $this->routesDirectory = $congiguration['routesDirectory'] ?? __DIR__ . '/../app';
        Env::$env_dirpath = $congiguration['rootDirectory'] ?? __DIR__ . "/../";
        Response::$viewsPath = $congiguration['viewDirectory'] ?? __DIR__ . '/../views';
    }
    public function run()
    {
        $request = Injector::Inject(Request::class);
        $regexPath = $this->generateRoutes();

        $requestPath = $request->path();

        // Recherche de la meilleure correspondance de route
        $matchedRoute = array_reduce(array_keys($regexPath), function ($best, $regex) use ($regexPath, $requestPath) {
            if (preg_match($regex, $requestPath, $matches)) {
                if (!$best || $regexPath[$regex]['matching_score'] > $best['matching_score']) {
                    return [
                        'regex' => $regex,
                        'params' => $matches,
                        'matching_score' => $regexPath[$regex]['matching_score'],
                        'path' => $regexPath[$regex]['path']
                    ];
                }
            }
            return $best;
        }, null);

        //print_r([$request->path(),$matchedRoute,$regexPath]);

        if (!$matchedRoute) {
            http_response_code(404);
            die("404 Not Found");
        }

        Path::$params = $matchedRoute['params'];
        $routePath = $this->routesDirectory . '/' . $matchedRoute['path'] . '/routes.php';
        
        if (!file_exists($routePath)) {
            http_response_code(404);
            die("404 Not Found" . $routePath);
        }

        // Pour chaque subdossier, charger le middleware
        $middlewares_after = [];
        $sub_path = '';
        foreach (['/', ...explode('/', $matchedRoute['path'])] as $subdirectory) {
            $sub_path = rtrim($sub_path . '/' . $subdirectory, '/');
            $middleware_path = $this->routesDirectory . $sub_path . '/middleware.php';
            if (file_exists($middleware_path)) {
                $mw = (static function ($middleware_path) {
                    require $middleware_path;
                    return [
                        "before" => $before ?? null,
                        "after" => $after ?? null
                    ];
                })($middleware_path);

                if (null != $mw["before"]) {
                    if (!is_array($mw["before"])) $mw["before"] = [$mw["before"]];
                    foreach ($mw['before'] as $middleware) {
                        $returns = $this->call_closure($middleware);
                        if ($returns instanceof Response) {
                            $returns->send();
                            die();
                        }
                    }
                }

                if (null != $mw["after"]) $middlewares_after = array_merge($middlewares_after, is_array($mw["after"]) ? $mw["after"] : [$mw["after"]]);
            }
        }

        $method = strtolower($request->method());
        $closure = (static function ($routePath, $method) {
            require $routePath;
            // Exécution de la fonction correspondant à la méthode HTTP
            if (!isset($$method) || !is_callable($$method)) {
                http_response_code(405);
                die("405 Method Not Allowed");
            }
            return $$method;
        })($routePath, $method);

        $returns = $this->call_closure($closure);
        // si $returns n'est pas une instance de Response, on l'injecte
        if (!($returns instanceof Response)) {
            $response = Injector::Inject(Response::class);
            if (is_array($returns)) {
                $response->json($returns);
            } else {
                $response->text($returns);
            }
        } else {
            $response = $returns;
        }

        $returns = null;
        foreach (array_reverse($middlewares_after) as $middleware) {
            $returns = $this->call_closure($middleware);
        }
        if ($returns instanceof Response) {
            $response = $returns;
        }

        if ($response instanceof Response) {
            $response->send();
        } else {
            echo $response;
        }
    }

    protected function call_closure($closure)
    {
        $dependencies = array_map(
            fn($param) => Injector::Inject((string) $param->getType()),
            (new ReflectionFunction($closure))->getParameters()
        );
        return $closure(...$dependencies);
    }

    protected function generateRoutes(): array
    {
        if (!is_dir($this->routesDirectory)) return [];

        // Exécute la commande pour lister les dossiers
        // Commandes pour Windows : FOR /D /R "C:\Your\Directory" %%i IN (*) DO @ECHO %%i
        exec("find " . escapeshellarg($this->routesDirectory) . " -type d", $output, $returnVar);
        if ($returnVar !== 0)  return [];

        // Générer les regex
        $regexPath = [];
        foreach ($output as $dossier) {
            $appDossier = ltrim(str_replace($this->routesDirectory, '', $dossier), '/');

            // On ignore les path qui se terminent avec un dossier entre parenthèses
            if (preg_match('/(\/|^)\([^)]+\)$/', $appDossier)) continue;

            $expressionRegex = $this->generateRegexFromPath($appDossier);
            $regexPath[$expressionRegex] = [
                "path" => $appDossier,
                "matching_score" => $this->countLiteralSegments($appDossier)
            ];
        }

        return $regexPath;
    }

    protected function generateRegexFromPath(string $path): string
    {
        return '#^' . preg_replace([
            '/\([^)]+\)/',           // Supprime les segments facultatifs entre parenthèses
            '/\/\//',                  // Remplace les doubles slashs
            '/^\//',                // Supprime le slash de début
            '/\/$/',                // Supprime le slash de fin
            '/\[([a-zA-Z0-9_-]+)\]/',  // Convertit [id] en (?<id>[^/]+)
            '/\[\.\.\.([a-zA-Z0-9_-]+)\]/' // Convertit [...path] en (?<path>.+)
        ], [
            '',
            '',
            '',
            '/',
            '(?<$1>[^/]+)',
            '(?<$1>.+)'
        ], $path) . '$#';
    }

    protected function countLiteralSegments(string $path): int
    {
        // Filtrage des segments qui ne sont ni entre [] ni entre ()
        $segments = array_filter(explode('/', trim($path, '/')), function ($segment) {
            $seg = ($segment[0] ?? '') . ($segment[-1] ?? '');
            return !in_array($seg, ['[]', '()', '{}']);
        });

        // Retourne le nombre de segments filtrés
        return count($segments);
    }
}
