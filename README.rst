==================================================
Question2Answer Plugin: Question Caching Plugin
==================================================
-----------
Description
-----------
This is a plugin_ for Question2Answer_ that caches each question in the file system.

------------
Installation
------------
#. Install Question2Answer_ if you haven't already.
#. Get the source code for this plugin directly from github_.
#. Extract the files.
#. Upload the files to a subfolder called ``q2apro-caching`` inside the ``qa-plugin`` folder of your Q2A installation.
#. **Create a folder ``qa-cache`` in the root of your q2a installation.**
#. Do NOT RENAME the plugin folder ``q2apro-caching`` or plugin cannot initialize.
#. Navigate to your site, go to **Admin -> Plugins**. Check if the plugin "q2apro caching" is listed and **enable** it.

----------
Notes
----------

This is a fork from [bndr](https://github.com/bndr/q2a-caching) + [sama55](https://github.com/sama55) + [stevenev](https://github.com/stevenev/q2a-caching).

The other versions of the plugin work well, however, they empty the entire file cache on incoming events. This plugin here only deletes the specific question page if it gets changed. So basically it should be more performant. Testers needed :)

----------
Disclaimer
----------
This is **beta** code. It is probably okay for production environments, but may not work exactly as expected. You bear the risk. Refunds will not be given!

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
See the GNU General Public License for more details.

---------
Copyright
---------
All code herein is OpenSource_. Feel free to build upon it and share with the world.
Wait a moment, the original author (Vadim Kr. bndr) put it under [CC BY-SA](https://creativecommons.org/licenses/by-sa/3.0/legalcode)

---------
About q2a
---------
Question2Answer_ is a free and open source PHP software for Q&A sites.

  
.. _github: https://github.com/q2apro/q2apro-caching
.. _OpenSource: http://www.gnu.org/licenses/gpl.html
.. _Question2Answer: http://www.question2answer.org/
