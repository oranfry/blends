<?php
namespace tablelink;

class groupuser extends \Tablelink
{
    public function __construct()
    {
        $this->tables = ['group', 'user'];
        $this->middle_table = 'tablelink_group_user';
        $this->ids = ['group', 'user'];
        $this->type = 'onemany';
    }
}
