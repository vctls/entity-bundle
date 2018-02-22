<?php

namespace Vctls\EntityBundle\Menu;

use Doctrine\ORM\EntityManager;
use Knp\Menu\FactoryInterface;
use Knp\Menu\MenuItem;
use Symfony\Bridge\Doctrine\ManagerRegistry;

/**
 * Class Builder
 *
 * @package Vctls\EntityBundle\Menu
 */
class Builder
{

    /** @var  FactoryInterface */
    private $factory;

    /**
     * Builder constructor.
     * @param FactoryInterface $factory
     */
    public function __construct(FactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Generate menu items for each entity CRUD page.
     *
     * @param ManagerRegistry $doctrine
     * @return \Knp\Menu\ItemInterface
     * @throws \Doctrine\ORM\ORMException
     */
    public function entityMenu(ManagerRegistry $doctrine)
    {
        $menu = $this->factory->createItem('root');

        $menu->addChild('Entity');

        /** @var EntityManager[] $ems */
        $ems = $doctrine->getManagers();

        // Get all entity class names.
        $entityClassNames = [];
        foreach ($ems as $em) {
            $classNames = $em->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
            foreach ($classNames as $className) {
                array_push($entityClassNames, $className);
            }
        }

        asort($entityClassNames);

        $entityClassNames = array_map(function($class){return explode("\\",$class);}, $entityClassNames);

        $this->addChildren($entityClassNames, $menu['Entity']);

        return $menu;
    }

    /**
     * Build the tree from the flat array,
     * and set the routes for each entity.
     *
     * @param array|string $names
     * @param MenuItem $menu
     * @param string $classFqn
     * @param bool $leaf
     */
    private function addChildren($names, $menu, $classFqn = "", $leaf = false)
    {
        if (is_array($names)) {
            // This is a branch.
            // For each level in the branch...
            for ( $i = 0 ; $i < count($names) ; $i++) {

                // If the current $name is not an array,
                // we are inside the branch.
                if (!is_array($names[$i])) {

                    if ($i != 0){
                        // If this is not the first menu item,
                        // move one node up before adding a child.
                        $menu = $menu[$names[$i-1]];
                    }

                    // If the current item is a string, meaning we're inside a branch,
                    // and there is no next item, then this is the leaf.
                    $leaf = !isset($names[$i + 1]);
                } else {

                    // If starting from the top of the branch,
                    // rebuild the clas FQN and pass it along.
                    $classFqn = implode('/',$names[$i]);
                }

                $this->addChildren($names[$i], $menu, $classFqn, $leaf);
            }
        } else {
            // This is a node.
            // If the node has not been added yet, add it.
            if (!$menu[$names]) {
                // If the node is a leaf, set the route,
                // and set the route parameter to the class FQN.
                $options = !$leaf ? [] :
                    [
                        'route' => 'entity_index',
                        'routeParameters' => [
                            'entityName' => $this->shortenFqn($classFqn),
                        ]
                    ];

                $menu->addChild($names, $options);
            }
        }
    }

    /**
     * Shorten the FQNs by replacing the beginning with the
     * corresponding alias.
     *
     * @param string $fqn
     * @return mixed|string
     */
    private function shortenFqn($fqn)
    {
        // TODO Retrieve the aliases from the configuration.
        $aliases = [
            //['AppBundle/Entity/SomeNamespace/', 'AliasForThatNamespace:'],
        ];

        foreach ($aliases as $alias) {
            $fqn = str_replace($alias[0],$alias[1],$fqn);
        }

        return $fqn;
    }

}