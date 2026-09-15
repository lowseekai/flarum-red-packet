import app from 'flarum/admin/app';
import Switch from 'flarum/common/components/Switch';

app.initializers.add('doingfb-red-packet-admin', () => {
  app.extensionData
    .for('doingfb-red-packet')
    .registerSetting(function () {
      return (
        <div className="Form-group">
          <Switch state={this.setting('doingfb-red-packet.enabled')() !== '0'} onchange={(value) => this.setting('doingfb-red-packet.enabled')(value ? '1' : '0')}>
            {app.translator.trans('doingfb-red-packet.admin.settings.enabled')}
          </Switch>
        </div>
      );
    })
    .registerSetting(function () {
      return (
        <div className="Form-group">
          <label>{app.translator.trans('doingfb-red-packet.admin.settings.min_amount')}</label>
          <div className="helpText">{app.translator.trans('doingfb-red-packet.admin.settings.min_amount_help')}</div>
          <input className="FormControl" type="number" min="1" step="1" bidi={this.setting('doingfb-red-packet.min_amount')} />
        </div>
      );
    })
    .registerSetting(function () {
      return (
        <div className="Form-group">
          <label>{app.translator.trans('doingfb-red-packet.admin.settings.max_amount')}</label>
          <div className="helpText">{app.translator.trans('doingfb-red-packet.admin.settings.max_amount_help')}</div>
          <input className="FormControl" type="number" min="1" step="1" bidi={this.setting('doingfb-red-packet.max_amount')} />
        </div>
      );
    })
    .registerSetting(function () {
      return (
        <div className="Form-group">
          <label>{app.translator.trans('doingfb-red-packet.admin.settings.max_count')}</label>
          <div className="helpText">{app.translator.trans('doingfb-red-packet.admin.settings.max_count_help')}</div>
          <input className="FormControl" type="number" min="1" max="500" step="1" bidi={this.setting('doingfb-red-packet.max_count')} />
        </div>
      );
    })
    .registerSetting(function () {
      return (
        <div className="Form-group">
          <label>{app.translator.trans('doingfb-red-packet.admin.settings.expires_minutes')}</label>
          <div className="helpText">{app.translator.trans('doingfb-red-packet.admin.settings.expires_minutes_help')}</div>
          <input className="FormControl" type="number" min="1" max="10080" step="1" bidi={this.setting('doingfb-red-packet.expires_minutes')} />
        </div>
      );
    })
    .registerPermission({
      icon: 'fas fa-envelope-open-text',
      label: app.translator.trans('doingfb-red-packet.admin.permissions.create'),
      permission: 'doingfb-red-packet.create',
    }, 'reply')
    .registerPermission({
      icon: 'fas fa-hand-holding-usd',
      label: app.translator.trans('doingfb-red-packet.admin.permissions.claim'),
      permission: 'doingfb-red-packet.claim',
    }, 'view');
});
