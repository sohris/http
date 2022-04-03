<?php

namespace Sohris\Http;

use React\Http\Message\Response as MessageResponse;
use RingCentral\Psr7\Stream;
use Sohris\Http\Utils;

class Response
{

    public static function Json(string $message, $status = 200)
    {
        return new MessageResponse($status, array(
            "Content-Type" => "application/json"
        ), json_encode(array("status" => "successful", "data" => Utils::utf8_encode_rec($message))));
    }
    public static function HTML(string $body, $status = 200)
    {
        return new MessageResponse($status, array(
            "Content-Type" => "text/html; charset=utf-8"
        ), $body);
    }
    public static function fileStream(string $file_name, Stream $stream, $status = 200)
    {
        return new MessageResponse($status, array(
            "Content-Description" => "File Transfer",
            "Content-Type" => "application/octet-stream",
            "Content-Disposition" => "attachent; filename=" . $file_name,
            "Content-Length" => $stream->getSize()
        ), $stream->getContents());
    }
}
