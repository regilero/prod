THIS IS AN EARLY **ALPHA** VERSION.
Big changes may come, lots of TODOS in the project

# PROD

This is a **Drupal (v7) module**.
It requires a php version >=5.5

The goal is to :

 * extract statistics on various internal (sizes of tables, number of active users, nodes by types, etc)
 * optionnaly store some history on these trackers (with a custom RRD --Round Robin Database-- implementation in the database)
 * use d3js to produce nice reports
 * provide drush entries for collecting stats, doing nagios checks, collecting tasks for external trackers like cacti
 * planned: tracking filesystems changes, watermarking the code and reporting new files or altered files.

## Warning

* Activating the rrd historical storage may produce a big size of data (but you'll get the trackers to follow that size)
* beta version
* I use elysia_cron to schedule and run the stats collectors (or drush), relying on the default Drupal cron is hazardous.

