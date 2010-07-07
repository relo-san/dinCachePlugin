<?php

/*
 * This file is part of the dinCachePlugin package.
 * (c) DineCat, 2010 http://dinecat.com/
 * 
 * For the full copyright and license information, please view the LICENSE file,
 * that was distributed with this package, or see http://www.dinecat.com/din/license.html
 */

/**
 * Listener for Doctrine record for cache manager
 * 
 * @package     dinCachePlugin
 * @subpackage  lib.record
 * @author      Nicolay N. Zyk <relo.san@gmail.com>
 */
class dinCacheDoctrineListener extends Doctrine_Record_Listener
{

    /**
     * Post save event
     * 
     * @param   Doctrine_Event  $event
     * @return  void
     */
    public function postSave( Doctrine_Event $event )
    {

        $obj = $event->getInvoker();
        sfContext::getInstance()->get( 'cache_manager' )->removeCacheForModel(
            $obj->getTable()->getComponentName(), $obj->toArray()
        );

    } // dinCacheDoctrineListener::postSave()


    /**
     * Post delete event
     * 
     * @param   Doctrine_Event  $event
     * @return  void
     */
    public function postDelete( Doctrine_Event $event )
    {

        $obj = $event->getInvoker();
        sfContext::getInstance()->get( 'cache_manager' )->removeCacheForModel(
            $obj->getTable()->getComponentName(), $obj->toArray()
        );

    } // dinCacheDoctrineListener::postDelete()

} // dinCacheDoctrineListener

//EOF