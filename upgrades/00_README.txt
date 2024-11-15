This folder contains SQL upgrade scripts and PHP upgrade scripts.

SQL UPGRADE SCRIPTS:
When upgrading Jethro you MUST run the relevant SQL upgrade scripts. For example if you're upgrading from 2.29 to 2.31 you should run upgrade-to-2.30.sql and upgrade-to-2.31.sql.

PHP UPGRADE SCRIPTS:
It is NOT essential to run the PHP upgrade scripts. The Jethro app will run these itself as necessary. But you can run them manually at upgrade time if you choose - AFTER the SQL upgrades.
