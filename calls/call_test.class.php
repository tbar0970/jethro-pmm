<?php
class Call_test extends Call
{
    function run()
    {
        echo "Hello world";
        throw new \RuntimeException("Something bad happened!");
    }
}