<?php

namespace Kalnoy\Nestedset;

use Illuminate\Database\Eloquent\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Model;

class Collection extends BaseCollection
{
    /**
     * Fill `parent` and `children` relationships for every node in collection.
     *
     * This will overwrite any previously set relations.
     *
     * @return $this
     */
    public function linkNodes()
    {
        if ($this->isEmpty()) return $this;

        $groupedChildren = $this->groupBy($this->first()->getParentIdName());

        /** @var NodeTrait|Model $node */
        foreach ($this->items as $node) {
            if ( ! isset($node->parent)) {
                $node->setRelation('parent', null);
            }

            $children = $groupedChildren->get($node->getKey(), [ ]);

            /** @var Model|NodeTrait $child */
            foreach ($children as $child) {
                $child->setRelation('parent', $node);
            }

            $node->setRelation('children', BaseCollection::make($children));
        }

        return $this;
    }

    /**
     * Build tree from node list. Each item will have set children relation.
     *
     * To successfully build tree "id", "_lft" and "parent_id" keys must present.
     *
     * If `$rootNodeId` is provided, the tree will contain only descendants
     * of the node with such primary key value.
     *
     * @param int|Model|null $root
     *
     * @return Collection
     */
    public function toTree($root = null)
    {
        $items = [ ];

        if ( ! $this->isEmpty()) {
            $this->linkNodes();

            $root = $this->getRootNodeId($root);

            /** @var Model|NodeTrait $node */
            foreach ($this->items as $node) {
                if ($node->getParentId() == $root) $items[] = $node;
            }
        }

        return new static($items);
    }

    /**
     * @param mixed $root
     *
     * @return int
     */
    protected function getRootNodeId($root = null)
    {
        if (NodeTrait::hasTrait($root)) {
            return $root->getKey();
        }

        // If root node is not specified we take parent id of node with
        // least lft value as root node id.
        if ($root === null) {
            $leastValue = null;

            /** @var Model|NodeTrait $node */
            foreach ($this->items as $node) {
                if ($leastValue === null || $node->getLft() < $leastValue) {
                    $leastValue = $node->getLft();
                    $root = $node->getParentId();
                }
            }
        }

        return $root;
    }
}