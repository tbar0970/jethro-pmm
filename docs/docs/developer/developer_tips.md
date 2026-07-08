## Useful Helper Functions
* _array_get($array, $index, $fallback)_ - if $array[$index] is set, it's returned; otherwise $fallback is returned.  Particularly useful when dealing with $_REQUEST.
* _ifdef($constantName, $fallback)_ - if a constant is defined with name $constantName, it's returned; otherwise $fallback is returned.


## Tips
* Remember the _empty()_ function can handle non-set array elements without erroring.  So we can do `if (empty($_REQUEST['something']))` will efficiently test whether the element is non-set, blank string, false, null, zero etc.
* Don't include a close-php tag at the very end of a file. Can cause problems with outputting whitespace.
* Turn off any editor settings that will auto-adjust the indentation/whitespace throughout every file.  Makes pull requests very messy.  Only change the lines you change.
