<?php

namespace Drupal\Prod\Stats\Drupal;


/**
 * Stats Drupal User
 */
interface UserInterface
{

    function query_daily_connected_users();

    function query_monthly_connected_users();

}
