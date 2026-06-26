<?php
//Configuration easyRedmine

return [
    // URL de base de l'instance easyRedmine
    'base_url' => 'https://redmine.interlab15.com/projects?set_filter=0&&column_names%5B%5D=id&column_names%5B%5D=name&column_names%5B%5D=priority&column_names%5B%5D=project_manager&group_by%5B%5D=&show_sum_row=0&show_sum_row=0&load_groups_opened=0&show_avatars=0&show_in_tree=0&type=EasyProjectQuery&sort=lft&outputs%5B%5D=list&&',

    // Clé d'accès API 
    'api_key'  => '1265b3656b537dae8320793eeb535e2383b9ce4c',

    // Délai max d'une requête (secondes)
    'timeout'  => 15,

    // Vérification SSL (True pour sécurisé, false pour désécurisé)
    'ssl_verify' => false,
];
