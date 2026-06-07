<?php

namespace App\Services\Compliance;

class XmlParser
{
    public static function loadString(string $xml, string $contextLabel): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $element = simplexml_load_string(
                $xml,
                \SimpleXMLElement::class,
                LIBXML_NONET,
            );

            if ($element === false) {
                $messages = array_map(
                    fn (\LibXMLError $error) => trim($error->message),
                    libxml_get_errors(),
                );
                libxml_clear_errors();

                $detail = implode('; ', array_filter($messages)) ?: 'unknown error';

                throw new \RuntimeException($contextLabel.' parse failed: '.$detail);
            }

            return $element;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
