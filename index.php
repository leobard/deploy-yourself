<?php

// https://getkirby.com/docs/guide/plugins/plugin-setup-composer#support-for-plugin-installation-without-composer
@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('leobard/deploy-yourself', [
  'routes' => function () {
    return [
      [
        /**
         * Note: this route is for documentation purposes for you reading this code.
         * If installed according to the README.md,
         * the deploy-yourself-hook.php would be called directly
         * but if not, then this is an untested fallback
         */
        'pattern' => 'deploy-yourself/hook',
        'method'  => 'GET|POST',
        'action'  => function () {
          $kirby = kirby();
          $deploy_yourself = new Leobard\DeployYourself\DeployYourself(
            kirby_root_config_path: $kirby->root('config'),
            get_parameters: $kirby->request()->get()
          );
          return $deploy_yourself->hook();
        }
      ]
    ];
  },
  'areas' => [
    'deployyourself' => [
      'label' => 'Deploy yourself',
      'icon'  => 'sitemap',
      'menu'  => true,
      'link'  => 'deploy-yourself/index',
      'views' => [
        [
          'pattern' => 'deploy-yourself/(:all)',
          'action'  => function ($file) {
            $data = [
              'component' => 'deploy-yourself',
              'title' => 'Deploy yourself',
              'props' => [],
            ];
            // Security: only admins
            if(kirby()->user()?->role() != 'admin') {
              $data['props']['message'] = 'Only available for admins';
              return $data;
            }
            $kirby = kirby();
            $deploy_yourself = new Leobard\DeployYourself\DeployYourself(
              kirby_root_config_path: $kirby->root('config'),
              get_parameters: []
            );
            $data['props']['logfiles'] = $deploy_yourself->log_files_list();
            // is a file selected?
            if ('index' != $file && '' != $file) {
              $data['props']['selectedfilename'] = $file;
              $data['props']['selectedfilecontent'] = $deploy_yourself->log_file_load($file);
            }
            return $data;
          }
        ],
      ],
     ],
  ],
]);