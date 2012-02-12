<?php

/**
 * Generates PDFs from HTML
 * Uses wkhtmltopdf on the backend.
 * See http://code.google.com/p/wkhtmltopdf/ for details about the options
 *
 * // Example:
 *   $pdf = new PDFGenerator();
 *   $pdf->setInputHTML($content);
 *   $pdf->setPageSize('57.15mm', '12.7mm');
 *   $pdf->setMargins(0);
 *   $pdf->streamToClient();
 *
 * @author Danny Beardsley
 */
class PDFGenerator {
   // Output modes
   public static $DOWNLOAD  = 'download';
   public static $TEMP_FILE = 'temp_file';

   protected $pathToBinary = '/usr/local/bin/wkhtmltopdf-i386';

   protected $options = array(
      // Be quiet by default
      'q' => true
   );

   protected $outputMode = 'download';
   protected $outputFilename;
   protected $inputFilename;

   // Captured stdout from the pdf generation
   protected $stdout;

   // Collection of generated temp files to be cleaned up afterward
   protected $tempFiles = array();

   /**
    * Generates a PDF from the options set on this instance and streams
    * it back to the client for viewing in the browser.
    */
   public function streamToClient() {
      $this->setOutputMode(self::$DOWNLOAD);
      return $this->generate();
   }

   /**
    * Generates a PDF according to the options set on this instance.
    */
   protected function generate() {
      $args = $this->buildCommandlineArguments();

      $command = $this->pathToBinary.' '.$args;

      if ($this->outputMode = self::$DOWNLOAD) {
         $this->outputPDFDownloadHeaders();
         passthru($command, $exit_code);
      } else {
         exec($command . " 2>&1", $output, $exit_code);
         $this->stdout = $output;
      }

      $this->cleanupTempFiles();

      $success = $exit_code == 0;
      if (!$success) {
         // TODO: Log the $output somewhere useful
         throw new DebugException("PDF generation failed");
      }

      return $success;
   }

   /**
    * Valid modes are defined at the top of the class
    */
   public function setOutputMode($mode) {
      $this->outputMode = $mode;
      switch ($mode) {
         case self::$TEMP_FILE:
            $this->outputFilename = tempnam('/tmp', 'pdf') . '.pdf';
            break;
      }
   }

   /**
    * Sets the HTML to be used for generating the PDF
    */
   public function setInputHTML($html) {
      $this->inputFilename = $this->getTempFilename();
      file_put_contents($this->inputFilename, $html);
   }

   /**
    * Set any commandline option
    *
    * $value is optional (the option is sent with no value "--name ")
    */
   public function setOption($name, $value=true) {
      $this->options[$name] = $value;
   }

   /**
    * Set the page size exactly. The default unit is mm, but should be explicit.
    *
    * Note: $pdf->setPageSize('234mm', '123mm');
    */
   public function setPageSize($width, $height) {
      $this->setOption('page-width',  $width);
      $this->setOption('page-height', $height);
   }

   /**
    * Set the page size by standard name (Letter, A4, ..)
    */
   public function setPageSizeByName($paperSizeName) {
      $this->setOption('page-width',  null);
      $this->setOption('page-height', null);
      $this->setOption('page-size', $paperSizeName);
   }

   /**
    * Set the Top, Right, Bottom, and Left margins. (default unit is mm)
    *
    * Note: if only $t is passed, ALL margins will be set to $t
    */
   public function setMargins($t,$r=null,$b=null,$l=null) {
      if ($r===null) {
         $r = $b = $l = $t;
      }
      $this->setOption('margin-top',    $t);
      $this->setOption('margin-right',  $r);
      $this->setOption('margin-bottom', $b);
      $this->setOption('margin-left',   $l);
   }

   protected function outputFilename() {
      switch ($this->outputMode) {
         case self::$DOWNLOAD:
            return '-';
         case self::$FILE:
            return $this->outputFilename;
      }
   }

   /**
    * Returns a string of shell-escpaed arguments that reflects the current
    * options and input/output modes. The returned string can be sent directly
    * to the binary on this commandline.
    */
   protected function buildCommandlineArguments() {
      $args = '';

      foreach ($this->options as $key => $value) {
         if ($value === null || $value === false)
            continue;

         $dash = (strlen($key) == 1) ? '-' : '--';

         if (true === $value) {
            // arguments that don't have values: " --option "
            $args .= " {$dash}{$key} ";
         } else {
            // arguments that have values: " --option value "
            $args .= " {$dash}{$key} " . escapeshellarg($value);
         }
      }

      $args .= ' '.escapeshellarg($this->inputFilename).
               ' '.escapeshellarg($this->outputFilename());
      return $args;
   }

   protected static function outputPDFDownloadHeaders($length = null) {
      header('Content-Description: File Transfer');
      header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
      header('Pragma: public');
      header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
      // force download dialog
      //header('Content-Type: application/force-download');
      // use the Content-Disposition header to supply a recommended filename
      //header('Content-Disposition: attachment; filename="'.basename($file).'";');
      // Multiple content-type headers for best support
      header('Content-Type: application/octet-stream', false);
      header('Content-Type: application/download', false);
      header('Content-Type: application/pdf', false);
      header('Content-Transfer-Encoding: binary');
      if ($length !== null)
         header('Content-Length: '.$length);
   }

   protected function getTempFilename($ext = 'html') {
      $tmp = tempnam('/tmp', 'pdf') . ".{$ext}";
      $this->tempFiles[] = $tmp;
      return $tmp;
   }

   protected function cleanupTempFiles() {
      foreach ($this->tempFiles as $filename)
         unlink($filename);
   }
}
