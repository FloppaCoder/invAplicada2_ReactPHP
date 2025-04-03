<?php

require 'vendor/autoload.php';

use React\Http\HttpServer;
use React\EventLoop\Factory;
use Psr\Http\Message\ServerRequestInterface;
use React\MySQL\Factory as MySQLFactory;
use React\MySQL\QueryResult;

$loop = Factory::create();
$dbFactory = new MySQLFactory($loop);
$db = $dbFactory->createLazyConnection('reactphp_user:12345@localhost/invaplicada2');

$server = new HttpServer(function (ServerRequestInterface $request) use ($loop, $db) {
    $path = $request->getUri()->getPath();

    switch ($path) {
        case '/':
            return new React\Http\Message\Response(
                200,
                ['Content-Type' => 'text/html'],
                file_get_contents(__DIR__ . '/index.html')
            );

        case '/contact':
            if ($request->getMethod() === 'POST') {
                return $request->getParsedBody()->then(
                    function ($postData) use ($db) {
                        if (!isset($postData['name']) || empty(trim($postData['name']))) {
                            return new React\Http\Message\Response(
                                400,
                                ['Content-Type' => 'text/plain'],
                                'Error: El campo nombre es obligatorio.'
                            );
                        }

                        $name = trim($postData['name']);
                        return $db->query('INSERT INTO datos (name) VALUES (?)', [$name])->then(
                            function () {
                                return new React\Http\Message\Response(
                                    200,
                                    ['Content-Type' => 'text/plain'],
                                    '¡Registro guardado exitosamente!'
                                );
                            },
                            function (Exception $e) {
                                return new React\Http\Message\Response(
                                    500,
                                    ['Content-Type' => 'text/plain'],
                                    'Error al insertar en la base de datos: ' . $e->getMessage()
                                );
                            }
                        );
                    },
                    function (Exception $e) {
                        return new React\Http\Message\Response(
                            400,
                            ['Content-Type' => 'text/plain'],
                            'Error procesando el formulario: ' . $e->getMessage()
                        );
                    }
                );
            } else {
                return new React\Http\Message\Response(
                    200,
                    ['Content-Type' => 'text/html'],
                    file_get_contents(__DIR__ . '/contact.html')
                );
            }

        case '/data':
            if ($request->getMethod() === 'GET') {
                return $db->query('SELECT * FROM datos')->then(
                    function (QueryResult $result) {
                        return new React\Http\Message\Response(
                            200,
                            ['Content-Type' => 'application/json'],
                            json_encode($result->resultRows)
                        );
                    },
                    function (Exception $e) {
                        return new React\Http\Message\Response(
                            500,
                            ['Content-Type' => 'text/plain'],
                            'Error en la base de datos: ' . $e->getMessage()
                        );
                    }
                );
            }

        case '/style.css':
            return new React\Http\Message\Response(
                200,
                ['Content-Type' => 'text/css'],
                file_get_contents(__DIR__ . '/style.css')
            );

        default:
            return new React\Http\Message\Response(
                404, 
                ['Content-Type' => 'text/plain'], 
                'Error 404: Pagina no encontrada en el sitio'
            );
    }
});

$socket = new React\Socket\SocketServer('127.0.0.1:8080', [], $loop);
$server->listen($socket);

echo "Exito! 🚀 Servidor ReactPHP corriendo en la ruta: http://127.0.0.1:8080\n";

$loop->run();
