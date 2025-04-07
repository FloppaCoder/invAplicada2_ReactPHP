<?php

require 'vendor/autoload.php';

use React\Http\HttpServer;
use React\EventLoop\Factory;
use Psr\Http\Message\ServerRequestInterface;
use React\MySQL\Factory as MySQLFactory;
use React\MySQL\QueryResult;

$loop = Factory::create();
$dbFactory = new MySQLFactory($loop);
$db = $dbFactory->createLazyConnection('root@localhost/invaplicada2');

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
                $postData = $request->getParsedBody();
        
                // Verificar si el campo 'name' está presente y no está vacío
                if (!isset($postData['name']) || empty(trim($postData['name']))) {
                    return new React\Http\Message\Response(
                        400, // Bad Request
                        ['Content-Type' => 'text/plain'],
                        'Error: El campo nombre es obligatorio.'
                    );
                }
        
                // Sanear el nombre antes de guardarlo
                $name = trim($postData['name']);
        
                // Realizar la inserción en la base de datos
                return $db->query('INSERT INTO contactos (name) VALUES (?)', [$name])->then(
                    function () {
                        // Redirigir a /contact con un parámetro de éxito
                        return new React\Http\Message\Response(
                            302, // Redirección
                            [
                                'Location' => '/contact?success=true' // Agregar parámetro de éxito a la URL
                            ]
                        );
                    },
                    function (Exception $e) {
                        // Manejo de error si no se pudo insertar en la base de datos
                        return new React\Http\Message\Response(
                            500, // Error interno del servidor
                            ['Content-Type' => 'text/plain'],
                            'Error al insertar en la base de datos: ' . $e->getMessage()
                        );
                    }
                );
            } else {
                // Responder con el formulario de contacto para el método GET
                return new React\Http\Message\Response(
                    200, // OK
                    ['Content-Type' => 'text/html'],
                    file_get_contents(__DIR__ . '/contact.html') // Cargar el formulario de contacto
                );
            }

        // Mostrar la vista con los contactos (HTML + cargar datos dinámicamente)
        case '/data':
            if ($request->getMethod() === 'GET') {
                // Cargar la vista HTML (data.html)
                $htmlContent = file_get_contents(__DIR__ . '/data.html');

                return new React\Http\Message\Response(
                    200,
                    ['Content-Type' => 'text/html'],
                    $htmlContent
                );
            }elseif ($request->getMethod() === 'DELETE') {
                // Obtener el ID de la URL
                $queryParams = $request->getQueryParams();
                $id = isset($queryParams['id']) ? $queryParams['id'] : null;

                return $db->query('DELETE FROM contactos WHERE id = ?', [$id])->then(
                    function () {
                        return new React\Http\Message\Response(
                            200, // OK
                            ['Content-Type' => 'text/plain'],
                            'Contacto eliminado correctamente.'
                        );
                    },
                    function (Exception $e) {
                        return new React\Http\Message\Response(
                            500, // Error interno
                            ['Content-Type' => 'text/plain'],
                            'Error al eliminar el contacto: ' . $e->getMessage()
                        );
                    }
                );
            }

        case '/get-data': // Obtener los datos de los contactos en formato JSON (usado por la tabla en JavaScript)
            if ($request->getMethod() === 'GET') {
                return $db->query('SELECT * FROM contactos')->then(
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
