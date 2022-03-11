<?php



namespace Sohris\Http\Annotations;

use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\UploadedFile;
use Sohris\Http\Exceptions\StatusHTTPException;

/**
 * @Annotation
 * 
 * @Target("METHOD")
 */
class Needed
{
    /**
     * @var array<string>
     */
    private $needed;


    function __construct(array $args)
    {
        $this->needed = $args['value'];
    }


    public function getNeeded()
    {
        return $this->needed;
    }


    public function valid($params)
    {
        return $this->needed == $params;
    }

    public function getNeededInRequest(ServerRequestInterface $request)
    {
        $needed = [];
        $content_type = $request->getHeader("Content-Type");
        if (!empty($content_type)) {
            if (
                strpos($content_type[0], "application/x-www-form-urlencoded") !== false ||
                strpos($content_type[0], "multipart/form-data") !== false
            ) {
                $needed = $request->getParsedBody();
            } else if (strpos($content_type[0], "application/json") !== false) {
                $temp = $request->getBody()->getContents();
                if ($temp) {
                    $needed = json_decode($temp, true);
                }
            }
        } else {
            $needed = $request->getQueryParams();
            if (empty($needed)) {
                $params = explode("&", $request->getUri()->getQuery());
                foreach ($params as $value) {
                    $values = explode("=", $value);
                    if (!empty($values) && $values[0] && $values[1]) {
                        $needed[$values[0]] = $values[1];
                    }
                }
            }
        }
        if (empty($needed))
            throw new StatusHTTPException("Invalid Parameters", 401);
        return self::filterNeeded($needed, $this->needed);
    }

    public static function filterNeeded(array $parameters, array $needed)
    {
        $filtered = [];

        foreach ($needed as $n) {
            if (!array_key_exists($n, $parameters))
                throw new StatusHTTPException("Invalid Parameters", 401);
            $filtered[$n] = $parameters[$n];
        }

        return $filtered;
    }

    public static function getFilesInRequest(ServerRequestInterface $request)
    {
        $content_type = $request->getHeader("Content-Type");
        if (empty($content_type))
            return [];

        if (
            strpos($content_type[0], "application/x-www-form-urlencoded") !== false ||
            strpos($content_type[0], "multipart/form-data") !== false
        ) {

            $files = $request->getUploadedFiles();
            if (empty($files))
                return [];

            $new_files = [];
            foreach ($files as $file) {

                if ($file->getError() != 0) {
                    switch ($file->getError()) {
                        case 1:
                            $errors[] = array(
                                "file" => $file->getClientFilename(),
                                "info" => "(" . $file->getError() . ") - File is to large (max. " . ini_get("upload_max_filesize") . ") ",
                            );
                            break;
                        default:
                            $errors[] = array(
                                "file" => $file->getClientFilename(),
                                "info" => "(" . $file->getError() . ") - Error in file upload!",
                            );
                    }
                }
            }

            if (!empty($errors)) {
                throw new StatusHTTPException($errors, 401);
            }

            foreach ($files as $name => $file) {

                $new_files[$name] = new UploadedFile(
                    $file->getStream(),
                    $file->getSize(),
                    $file->getError(),
                    $file->getClientFilename(),
                    $file->getClientMediaType()
                );
            }
            return $new_files;
        }
        return [];
    }
}
