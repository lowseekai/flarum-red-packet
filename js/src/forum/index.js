import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Component from 'flarum/common/Component';
import Model from 'flarum/common/Model';
import Button from 'flarum/common/components/Button';
import Modal from 'flarum/common/components/Modal';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import TextEditor from 'flarum/common/components/TextEditor';
import TextEditorButton from 'flarum/common/components/TextEditorButton';
import CommentPost from 'flarum/forum/components/CommentPost';
import avatar from 'flarum/common/helpers/avatar';
import username from 'flarum/common/helpers/username';
import humanTime from 'flarum/common/helpers/humanTime';

class RedPacket extends Model {}
Object.assign(RedPacket.prototype, {
  userId: Model.attribute('userId'),
  totalAmount: Model.attribute('totalAmount'),
  totalCount: Model.attribute('totalCount'),
  claimedAmount: Model.attribute('claimedAmount'),
  claimedCount: Model.attribute('claimedCount'),
  remainingAmount: Model.attribute('remainingAmount'),
  distribution: Model.attribute('distribution'),
  greeting: Model.attribute('greeting'),
  status: Model.attribute('status'),
  claimedByActor: Model.attribute('claimedByActor'),
  actorClaimAmount: Model.attribute('actorClaimAmount'),
  canClaim: Model.attribute('canClaim'),
  expiresAt: Model.attribute('expiresAt', Model.transformDate),
  createdAt: Model.attribute('createdAt', Model.transformDate),
  user: Model.hasOne('user'),
});

const markerPattern = /\[redpacket\s+id=(\d+)\]|\[\[doingfb-red-packet:(\d+)\]\]/g;

function apiUrl(path) {
  return `${String(app.forum.attribute('apiUrl') || '/api').replace(/\/$/, '')}${path}`;
}

function moneyName() {
  return String(app.forum.attribute('antoinefr-money.moneyname') || '[money]');
}

function formatMoney(amount) {
  const value = Number(amount || 0).toLocaleString(undefined, { maximumFractionDigits: 4 });
  return moneyName().replace('[money]', value);
}

function redPacketEnabled() {
  return !!(app.forum && app.forum.attribute('doingfb-red-packet.enabled'));
}

class CreateRedPacketModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);

    this.totalAmount = '';
    this.totalCount = '1';
    this.greeting = '恭喜发财，大吉大利';
  }

  className() {
    return 'DoingfbRedPacketModal Modal--small';
  }

  title() {
    return '发红包';
  }

  content() {
    const balance = app.session.user ? app.session.user.attribute('money') : null;

    return (
      <div className="Modal-body">
        <div className="Form-group">
          <label>总金额</label>
          <input
            className="FormControl"
            type="number"
            min={app.forum.attribute('doingfb-red-packet.minAmount') || 1}
            max={app.forum.attribute('doingfb-red-packet.maxAmount') || 1000}
            step="0.0001"
            value={this.totalAmount}
            oninput={(event) => {
              this.totalAmount = event.target.value;
            }}
          />
          <div className="helpText">
            可发 {formatMoney(app.forum.attribute('doingfb-red-packet.minAmount'))} - {formatMoney(app.forum.attribute('doingfb-red-packet.maxAmount'))}
          </div>
        </div>

        <div className="Form-group">
          <label>红包个数</label>
          <input
            className="FormControl"
            type="number"
            min="1"
            max={app.forum.attribute('doingfb-red-packet.maxCount') || 50}
            step="1"
            value={this.totalCount}
            oninput={(event) => {
              this.totalCount = event.target.value;
            }}
          />
        </div>

        <div className="Form-group">
          <label>祝福语</label>
          <input
            className="FormControl"
            maxlength="120"
            value={this.greeting}
            oninput={(event) => {
              this.greeting = event.target.value;
            }}
          />
        </div>

        {balance !== null ? <p className="helpText">当前余额：{formatMoney(balance)}</p> : null}

        <div className="Form-group">
          <Button className="Button Button--primary" type="submit" loading={this.loading}>
            生成红包并插入帖子
          </Button>
        </div>
      </div>
    );
  }

  onsubmit(event) {
    event.preventDefault();
    this.loading = true;

    app.request({
      method: 'POST',
      url: apiUrl('/doingfb-red-packets'),
      body: {
        data: {
          type: 'doingfb-red-packets',
          attributes: {
            totalAmount: this.totalAmount,
            totalCount: this.totalCount,
            greeting: this.greeting,
          },
        },
      },
    }).then((payload) => {
      const packet = app.store.pushPayload(payload);
      const marker = `\n[redpacket id=${packet.id()}]\n`;

      if (this.attrs.editor && this.attrs.editor.insertAtCursor) {
        this.attrs.editor.insertAtCursor(marker);
      }

      this.hide();
      app.alerts.show({ type: 'success' }, '红包已创建并插入。');
    }).catch(() => {
      this.loading = false;
      m.redraw();
    });
  }
}

class RedPacketCard extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.claiming = false;
    this.packet = null;
    this.load();
  }

  load() {
    return app.request({
      method: 'GET',
      url: apiUrl(`/doingfb-red-packets/${this.attrs.id}`),
    }).then((payload) => {
      this.packet = app.store.pushPayload(payload);
      this.loading = false;
      m.redraw();
    }).catch(() => {
      this.loading = false;
      m.redraw();
    });
  }

  view() {
    if (this.loading) {
      return <div className="DoingfbRedPacketCard is-loading"><LoadingIndicator size="small" /></div>;
    }

    if (!this.packet) {
      return <div className="DoingfbRedPacketCard is-error">红包加载失败</div>;
    }

    const user = this.packet.user && this.packet.user();
    const status = this.packet.status();
    const claimed = this.packet.claimedByActor();
    const canClaim = this.packet.canClaim();

    return (
      <div className={`DoingfbRedPacketCard is-${status}`}>
        <div className="DoingfbRedPacketCard-icon"><i className="fas fa-gift" /></div>
        <div className="DoingfbRedPacketCard-main">
          <div className="DoingfbRedPacketCard-meta">
            {user ? avatar(user) : null}
            <span>{user ? username(user) : '用户'} 的红包</span>
            {this.packet.createdAt() ? <time>{humanTime(this.packet.createdAt())}</time> : null}
          </div>
          <strong>{this.packet.greeting() || '恭喜发财，大吉大利'}</strong>
          <p>
            已领 {this.packet.claimedCount()} / {this.packet.totalCount()} 个，
            共 {formatMoney(this.packet.totalAmount())}
          </p>
          {claimed ? <em>你已领取 {formatMoney(this.packet.actorClaimAmount())}</em> : this.statusText(status)}
        </div>
        <div className="DoingfbRedPacketCard-action">
          <Button
            className="Button Button--primary"
            loading={this.claiming}
            disabled={!canClaim || this.claiming}
            onclick={() => this.claim()}
          >
            {claimed ? '已领取' : status === 'open' ? '领取' : this.statusButtonText(status)}
          </Button>
        </div>
      </div>
    );
  }

  statusText(status) {
    const map = {
      claimed: '红包已领完',
      expired: '红包已过期',
      refunded: '红包已退款',
    };

    return status !== 'open' ? <em>{map[status] || '红包不可领取'}</em> : null;
  }

  statusButtonText(status) {
    const map = {
      claimed: '已领完',
      expired: '已过期',
      refunded: '已退款',
    };

    return map[status] || '不可领取';
  }

  claim() {
    if (!this.packet || this.claiming) return;

    this.claiming = true;
    app.request({
      method: 'POST',
      url: apiUrl(`/doingfb-red-packets/${this.packet.id()}/claim`),
      body: { data: { type: 'doingfb-red-packet-claims', attributes: {} } },
    }).then((payload) => {
      this.packet = app.store.pushPayload(payload);
      this.claiming = false;
      app.alerts.show({ type: 'success' }, `领取成功：${formatMoney(this.packet.actorClaimAmount())}`);
      m.redraw();
    }).catch(() => {
      this.claiming = false;
      this.load();
    });
  }
}

function replaceMarkersInElement(element) {
  if (!element || element.dataset.doingfbRedPacketScanned === '1') return;
  element.dataset.doingfbRedPacketScanned = '1';

  const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, {
    acceptNode(node) {
      if (!node.nodeValue || !markerPattern.test(node.nodeValue)) {
        markerPattern.lastIndex = 0;
        return NodeFilter.FILTER_REJECT;
      }

      markerPattern.lastIndex = 0;
      return NodeFilter.FILTER_ACCEPT;
    },
  });
  const nodes = [];

  while (walker.nextNode()) nodes.push(walker.currentNode);

  nodes.forEach((node) => {
    const text = node.nodeValue;
    const fragment = document.createDocumentFragment();
    let lastIndex = 0;
    let match;

    markerPattern.lastIndex = 0;
    while ((match = markerPattern.exec(text)) !== null) {
      if (match.index > lastIndex) {
        fragment.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));
      }

      const packetId = match[1] || match[2];
      const mount = document.createElement('span');
      mount.className = 'DoingfbRedPacketMount';
      mount.dataset.redPacketId = packetId;
      fragment.appendChild(mount);
      mountRedPacketElement(mount);
      lastIndex = markerPattern.lastIndex;
    }

    if (lastIndex < text.length) {
      fragment.appendChild(document.createTextNode(text.slice(lastIndex)));
    }

    node.parentNode.replaceChild(fragment, node);
  });
}

function mountRedPacketElement(element) {
  if (!element || element.dataset.doingfbRedPacketMounted === '1') return;

  const packetId = element.dataset.redPacketId;
  if (!packetId) return;

  element.dataset.doingfbRedPacketMounted = '1';
  m.mount(element, { view: () => <RedPacketCard id={packetId} /> });
}

function scanRedPacketMarkers(root = document) {
  if (!redPacketEnabled()) return;

  if (root.matches && root.matches('.Post-body, .Post-preview, .DoingfbChatMessage-text')) {
    replaceMarkersInElement(root);
  }

  if (root.matches && root.matches('.DoingfbRedPacketMount[data-red-packet-id]')) {
    mountRedPacketElement(root);
  }

  root.querySelectorAll('.Post-body, .Post-preview, .DoingfbChatMessage-text').forEach(replaceMarkersInElement);
  root.querySelectorAll('.DoingfbRedPacketMount[data-red-packet-id]').forEach(mountRedPacketElement);
}

let observer = null;

function bootMarkerScanner() {
  if (!app.forum) return;

  scanRedPacketMarkers();

  if (!observer && document.body) {
    observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === Node.ELEMENT_NODE) {
            scanRedPacketMarkers(node);
          }
        });
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }
}

app.initializers.add('doingfb-red-packet', () => {
  app.store.models['doingfb-red-packets'] = RedPacket;

  extend(TextEditor.prototype, 'toolbarItems', function (items) {
    if (!redPacketEnabled() || !app.forum.attribute('doingfb-red-packet.canCreate')) return;

    items.add('doingfb-red-packet', (
      <TextEditorButton
        icon="fas fa-gift"
        title="发红包"
        onclick={() => app.modal.show(CreateRedPacketModal, { editor: this.attrs.composer && this.attrs.composer.editor })}
      />
    ));
  });

  extend(CommentPost.prototype, 'oncreate', function () {
    scanRedPacketMarkers(this.element);
  });

  extend(CommentPost.prototype, 'onupdate', function () {
    scanRedPacketMarkers(this.element);
  });

  // Flarum runs initializers before app.forum is assigned, so defer DOM scanning
  // until after the forum payload has been pushed and the app has mounted.
  setTimeout(bootMarkerScanner, 0);
});
