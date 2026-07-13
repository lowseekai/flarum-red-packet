import app from 'flarum/admin/app';

app.initializers.add('doingfb-red-packet-admin', () => {
  app.extensionData
    .for('doingfb-red-packet')
    .registerSetting({
      setting: 'doingfb-red-packet.enabled',
      type: 'boolean',
      label: app.translator.trans('doingfb-red-packet.admin.settings.enabled'),
    })
    .registerSetting({
      setting: 'doingfb-red-packet.min_amount',
      type: 'number',
      label: app.translator.trans('doingfb-red-packet.admin.settings.min_amount'),
      help: app.translator.trans('doingfb-red-packet.admin.settings.min_amount_help'),
    })
    .registerSetting({
      setting: 'doingfb-red-packet.max_amount',
      type: 'number',
      label: app.translator.trans('doingfb-red-packet.admin.settings.max_amount'),
      help: app.translator.trans('doingfb-red-packet.admin.settings.max_amount_help'),
    })
    .registerSetting({
      setting: 'doingfb-red-packet.max_count',
      type: 'number',
      label: app.translator.trans('doingfb-red-packet.admin.settings.max_count'),
      help: app.translator.trans('doingfb-red-packet.admin.settings.max_count_help'),
    })
    .registerSetting({
      setting: 'doingfb-red-packet.expires_minutes',
      type: 'number',
      label: app.translator.trans('doingfb-red-packet.admin.settings.expires_minutes'),
      help: app.translator.trans('doingfb-red-packet.admin.settings.expires_minutes_help'),
    })
    .registerPermission({
      icon: 'fas fa-gift',
      label: app.translator.trans('doingfb-red-packet.admin.permissions.create'),
      permission: 'doingfb-red-packet.create',
    }, 'reply')
    .registerPermission({
      icon: 'fas fa-hand-holding-usd',
      label: app.translator.trans('doingfb-red-packet.admin.permissions.claim'),
      permission: 'doingfb-red-packet.claim',
    }, 'view');
});
