<?php

namespace Larastart\Support;


use Symfony\Component\HttpFoundation\StreamedResponse;

class Printer
{
    private $handle;
    private $delimiter;
    private $enclosure;

    public static function handle(Exporter $exporter, $delimiter = ",", $enclosure = '"')
    {
        $instance = new static($delimiter, $enclosure);
        $exporter->handle($instance);
        $instance->close();
    }

    public function printLn($line)
    {
        fputcsv($this->handle, $line, $this->delimiter, $this->enclosure);
    }

    private function __construct($delimiter, $enclosure)
    {
        $this->handle = fopen('php://output', 'w');
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
    }

    private function close()
    {
        fclose($this->handle);
    }
}

class Exporter
{
    private $cb;

    public function __construct(callable $cb = null)
    {
        $this->cb = $cb;
    }

    public function handle(Printer $printer)
    {
        call_user_func($this->cb, $printer);
    }

    public function streamedResponse($filename = 'test.csv', $contentType = 'text/csv; charset=utf-8')
    {
        return new StreamedResponse(function () {
            Printer::handle($this);
        }, 200, [
            'Content-Encoding' => 'UTF-8',
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }

}
