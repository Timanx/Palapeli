<?php

interface IDiscussionControlFactory
{
    /** @return DiscussionControl */
    function create();
}