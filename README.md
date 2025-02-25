# Jethro Pastoral Ministry Manager

Jethro Pastoral Ministry Manager is a web-based tool which helps churches keep track of people, families, groups, attendance, pastoral tasks, church services, rosters and documents.  Jethro doesn't force you to work in a particular way but gives you flexible, lightweight tools to support your own style of ministry.  

The Jethro software is free and open source (GPL) and runs on a standard [LAMP](https://en.wikipedia.org/wiki/LAMP_%28software_bundle%29) web server.  Jethro's real advantages come to the fore when it's running on a proper web server, but it can also be run on a single PC using [XAMPP](XAMPP).

Jethro PMM is the software that powers online services such as [Easy Jethro](https://easyjethro.com.au) who also offer a [demo system](https://easyjethro.com.au/demo/).

# Download and install

Download the latest version of Jethro from the [releases page](https://github.com/tbar0970/jethro-pmm/releases)

System requirements are:
* MySQL 8.0 or above
    * with [ONLY_FULL_GROUP_BY](https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_only_full_group_by) disabled
* PHP 5.3.0 or above
    * with [gettext extension](https://www.php.net/manual/en/book.gettext.php) enabled
    * [GD library](https://www.php.net/manual/en/book.image.php) recommended, to manage the size of uploaded photos
    * with [curl extension](https://www.php.net/manual/en/book.curl.php) enabled, if you intend to use the Mailchimp integration
* Some web server (apache suggested)

The steps to install are:

1. Unzip the files into a web-accessible folder on your web server
2. Create a mysql database and database user for your jethro system to use. If asked, choose utf8_unicode_ci as the character set and collation.
3. Edit Jethro's configuration file conf.php and fill in the essential details (system name, URL, database details).  Further explanation can be found inside the file.
4. Open the jethro system URL in your web browser
    In your web browser, the Jethro installer will start automatically and will prompt you for details to create the initial user account.  When the installer completes, it will prompt you to log into the installed system.

# Documentation

## User Documentation

Some documentation articles are hosted by [Easy Jethro](https://easyjethro.com.au/support/)

# Support and Discussion

If you're having trouble with Jethro and think you might have found a bug, please [open an issue on github](https://github.com/tbar0970/jethro-pmm/issues/new).

If you have an idea for a new feature, please [look if somebody has already requested it](https://github.com/tbar0970/jethro-pmm/issues?q=is%3Aopen+is%3Aissue+label%3Afeature-request) and if not, [open a new issue](https://github.com/tbar0970/jethro-pmm/issues/new).

General questions about Jethro and how to use it can also be done in github issues

The Jethro developers try to respond to issues in a timely manner, but for real-time support you may need to sign up for a hosting service such as [Easy Jethro](https://easyjethro.com.au).
 
# Data Model
The following is a high-level overview of the objects in Jethro and how they relate.
* A **person** has a name and various other properties.
* Every person belongs to exactly one **family**.  A family is a collection of persons who live at the same address and are in some way related.
* Every person belongs to exactly one **congregation** - the main grouping within a church.  (One exception to this is persons with status 'contact', who can be congregation-less).
* A person can belong to several **groups** which can represent many things such as bible studies, volunteers for some role, people who are undergoing a welcoming process, people who have completed some particular training course, etc etc.
* A person's **attendance** at their congregation or a certain group can be recorded week by week
* **Notes** can be added to persons or families.  These may simply record extra free-form information about the person, or may be assigned to a Jethro user for action (eg "call Mr Smith").
* A **report** can show persons who match certain rules regarding their personal details, group memberships etc etc.
* A **service** is when a congregation meets together on a certain date, and has various details such as the topic and what bible passages are to be used.
* A service's **run sheet** can contain several **service components** such as songs, prayers, etc, selected from the congregation's repertoire.
* A **roster role** is some role to be played in a service, eg reading the bible
* A **roster view** is a collection of roster roles which are viewed or edited together (eg all the roles for the evening service, or the 'preacher' roles for all services)
* A **roster assignment** is when a person is assigned to a certain roster role for a particular service.

An extensive list of Jethro's capabilities is available on the [Easy Jethro site](https://easyjethro.com.au/#features)

# Naming

Jethro is designed to facilitate and encourage good team ministry, so its name comes from Exodus 18:13-23 where Moses is introduced by his father in law to the important skill of **delegation**.  His father in law was named Jethro.

# Acknowledgements
Jethro development has been sponsored or contributed to by several churches worldwide:
* [Christ Church Inner West Anglican Community](http://cciw.org.au), Sydney, Australia (founding sponsor)
* [Redlands Presbyterian Church](https://redlands.org.au/), Queensland, Australia (sponsor of service planning features)
* [St Peter's Woolton](https://www.stpeters-woolton.org.uk), Liverpool, UK (sponsor of date field and photo features)
* [Coast Evangelical Church](https://www.coastec.net.au)</a>, Forster, Australia (sponsor of group-membership statuses, attendance enhancements and more)
* [St George North Anglican Church](https://www.snac.org.au)</a>, Sydney, Australia (contributor of vCard export)
* [Macquarie Anglican Church](http://www.macquarieanglican.org/)</a>, Sydney, Australia (contributor of note-search and SMS-family feature)
* [Dalby Presbyterian Church](http://www.dpc.cc/)</a>, Queensland, Australia (sponsor of edit/delete note features and family photos)
* [Professional Standards Unit](https://safeministry.org.au), Anglican Diocese of Sydney (sponsor of custom fields etc)
There are also several github contributors whose input is invaluable.
