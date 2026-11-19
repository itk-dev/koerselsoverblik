<?php

declare(strict_types=1);

namespace App\Twig;

use App\Menu\MainMenu;
use App\Menu\MenuBuilder;
use App\Menu\MenuLink;
use Twig\Attribute\AsTwigFunction;

/**
 * Gives the templates the main menu links that the current user can see.
 */
final readonly class MenuExtension
{
    public function __construct(
        private MenuBuilder $menuBuilder,
    ) {
    }

    /**
     * @return list<MenuLink>
     */
    #[AsTwigFunction('main_menu')]
    public function mainMenu(): array
    {
        return $this->menuBuilder->build(MainMenu::items());
    }
}
