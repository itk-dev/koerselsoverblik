<?php

namespace App\Twig;

use App\Menu\MainMenu;
use App\Menu\MenuBuilder;
use App\Menu\MenuItem;
use Twig\Attribute\AsTwigFunction;

/**
 * Gives the templates the main menu items that the current user can see.
 */
final readonly class MenuExtension
{
    public function __construct(
        private MenuBuilder $menuBuilder,
    ) {
    }

    /**
     * @return list<MenuItem>
     */
    #[AsTwigFunction('main_menu')]
    public function mainMenu(): array
    {
        return $this->menuBuilder->build(MainMenu::items());
    }
}
