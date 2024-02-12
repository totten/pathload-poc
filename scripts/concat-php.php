<?php

function main($prog, ...$files): int {

  $first = TRUE;

  foreach ($files as $file) {
    if (!$first) {
      echo '?>';
    }
    echo file_get_contents($file);
    $first = FALSE;
  }

  return 0;
}

exit(main(...$argv));