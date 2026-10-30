<?php

namespace App\Menu;

/**
 * The definition of the main menu. The route names are the names that the later tasks use.
 *
 * MenuBuilder hides the items whose route does not exist yet.
 */
final class MainMenu
{
    /**
     * @return list<MenuItem>
     */
    public static function items(): array
    {
        return [
            new MenuItem('menu.overview', 'overview', 'ROLE_VIEW_OVERVIEW', icon: 'dashboard'),
            new MenuItem('menu.clear_driving_data', 'clear_driving_data', 'ROLE_CLEAR_DRIVING_DATA', icon: 'ban'),
            new MenuItem('menu.daily_counts', 'daily_counts', 'ROLE_EDIT_DAILY_COUNTS', icon: 'calendar'),
            new MenuItem('menu.administration', children: [
                new MenuItem('menu.suppliers', 'admin_supplier_index', 'ROLE_MANAGE_SUPPLIERS', icon: 'car'),
                new MenuItem('menu.users', 'admin_user_index', 'ROLE_MANAGE_USERS', icon: 'users'),
            ]),
        ];
    }
}
