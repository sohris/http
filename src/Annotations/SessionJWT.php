<?php


namespace Sohris\Http\Annotations;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface;
use RingCentral\Psr7\Request;
use Sohris\Core\Utils as CoreUtils;
use Sohris\Http\Exceptions\StatusHTTPException;
use Sohris\Http\Utils;

/**
 * @Annotation
 * 
 * @Target("METHOD")
 */
class SessionJWT
{
    /**
     * @var boolean
     */
    private $auth = false;

    private static $key = '';

    function __construct(array $args)
    {
        
        $config = CoreUtils::getConfigFiles('http');
        self::$key = $config['jwt_key'];
        echo self::$key . PHP_EOL;
        $this->auth = $args['value'];
    }

    public static function getSession(ServerRequestInterface $request)
    {
        if (empty($request->getHeader('Authorization')))
            throw new StatusHTTPException("Not Authorized", 403);

        $token = explode('Bearer ', $request->getHeader('Authorization')[0])[1];
        try {
            return JWT::decode($token, new Key(self::$key,'HS256'));
        } catch (Exception $e) {
            throw new StatusHTTPException(json_encode(["error" => "Authorization Failed", "description" => $e->getMessage()]), 403);
        }
    }
}
