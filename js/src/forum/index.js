/*global s9e*/

import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import Component from 'flarum/common/Component';
import Model from 'flarum/common/Model';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Modal from 'flarum/common/components/Modal';
import Badge from 'flarum/common/components/Badge';
import Discussion from 'flarum/common/models/Discussion';
import Post from 'flarum/forum/components/CommentPost';
import ComposerState from 'flarum/forum/states/ComposerState';
import classList from 'flarum/common/utils/classList';

const markerPattern = /\[redpacket\s+id=(\d+)\]|\[\[doingfb-red-packet:(\d+)\]\]/gi;
const redPacketIcon = 'fas fa-envelope-open-text';

class RedPacket extends Model {}

Object.assign(RedPacket.prototype, {
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
  user: Model.hasOne('user'),
});

function apiUrl(path) {
  const base = String(app.forum?.attribute('apiUrl') || '/api').replace(/\/$/, '');

  return `${base}${path}`;
}

function forumAttribute(name, fallback = null) {
  return app.forum?.attribute(name) ?? fallback;
}

function currencyLabel(amount) {
  return `${Number(amount || 0).toLocaleString()} ${forumAttribute('redPacketCurrencyName', '积分')}`;
}

function translationText(key, params) {
  const value = app.translator.trans(key, params);

  if (Array.isArray(value)) {
    return value
      .filter((part) => typeof part === 'string' || typeof part === 'number')
      .join('');
  }

  return typeof value === 'string' ? value : String(value ?? '');
}

function idsFromText(text) {
  const ids = [];
  const seen = new Set();
  let match;

  markerPattern.lastIndex = 0;

  while ((match = markerPattern.exec(text || '')) !== null) {
    const id = String(match[1] || match[2]);

    if (!seen.has(id)) {
      seen.add(id);
      ids.push(id);
    }
  }

  markerPattern.lastIndex = 0;

  return ids;
}

function rememberPending(composer, id) {
  composer.redPacketPendingIds ||= [];

  if (!composer.redPacketPendingIds.includes(String(id))) {
    composer.redPacketPendingIds.push(String(id));
  }
}

function cancelPending(ids) {
  [...new Set((ids || []).map(String).filter(Boolean))].forEach((id) => {
    app.request({
      method: 'DELETE',
      url: apiUrl(`/doingfb-red-packets/${id}`),
    }).catch(() => {});
  });
}

function syncPending(composer, content) {
  const pending = composer?.redPacketPendingIds || [];
  const active = idsFromText(content);
  const removed = pending.filter((id) => !active.includes(String(id)));

  if (!removed.length) {
    return;
  }

  composer.redPacketPendingIds = pending.filter((id) => active.includes(String(id)));
  cancelPending(removed);
}

function canCreateRedPacket() {
  return !!forumAttribute('canCreateRedPacket');
}

function showModal(componentClass, attrs = {}) {
  // Flarum 2 expects extension modals to be loaded through the async modal API.
  return app.modal.show(() => Promise.resolve({ default: componentClass }), attrs);
}

function findChildByClassName(vnode, className) {
  if (!vnode) {
    return null;
  }

  const classes = vnode?.attrs?.className;

  if (typeof classes === 'string' && classes.split(/\s+/).includes(className)) {
    return vnode;
  }

  if (!Array.isArray(vnode.children)) {
    return null;
  }

  return (
    vnode.children.find((child) => {
      const childClasses = child?.attrs?.className;

      return (
        typeof childClasses === 'string' &&
        childClasses.split(/\s+/).includes(className)
      );
    }) ?? null
  );
}

function placeRedPacketAfterLottery(vnode) {
  const body = findChildByClassName(vnode?.children?.[0], 'ComposerBody');
  const content = findChildByClassName(body, 'ComposerBody-content');
  const header = findChildByClassName(content, 'ComposerBody-header');

  if (!Array.isArray(header?.children)) {
    return;
  }

  const redPacketIndex = header.children.findIndex((child) =>
    child?.attrs?.className?.split?.(/\s+/).includes('item-redPacket')
  );
  const lotteryIndex = header.children.findIndex((child) =>
    child?.attrs?.className?.split?.(/\s+/).includes('item-lottery')
  );

  if (redPacketIndex < 0 || lotteryIndex < 0 || redPacketIndex === lotteryIndex + 1) {
    return;
  }

  const [redPacketItem] = header.children.splice(redPacketIndex, 1);
  const nextLotteryIndex = header.children.findIndex((child) =>
    child?.attrs?.className?.split?.(/\s+/).includes('item-lottery')
  );

  header.children.splice(nextLotteryIndex + 1, 0, redPacketItem);
}

class CreateRedPacketModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);

    this.totalAmount = '';
    this.totalCount = '1';
    this.distribution = 'average';
    this.greeting = translationText('doingfb-red-packet.forum.modal.default_greeting');
  }

  className() {
    return 'DoingfbRedPacketModal Modal--small';
  }

  title() {
    return app.translator.trans('doingfb-red-packet.forum.modal.title');
  }

  content() {
    const maxAmount = Number(forumAttribute('redPacketMaxAmount', 1000));
    const maxCount = Number(forumAttribute('redPacketMaxCount', 50));
    const amount = Number(this.totalAmount || 0);
    const allowedCount = amount > 0 ? Math.min(maxCount, Math.floor(amount)) : maxCount;
    const balance = app.session.user?.attribute('pointBalance');

    return (
      <form className="Modal-body" onsubmit={this.onsubmit.bind(this)}>
        <div className="Form-group">
          <label>{app.translator.trans('doingfb-red-packet.forum.modal.total_amount')}</label>
          <input
            className="FormControl"
            type="number"
            min="1"
            max={maxAmount}
            step="1"
            value={this.totalAmount}
            oninput={(event) => {
              this.totalAmount = event.target.value;
            }}
          />
          <div className="helpText">
            {app.translator.trans('doingfb-red-packet.forum.modal.total_amount_help', {
              min: currencyLabel(forumAttribute('redPacketMinAmount', 1)),
              max: currencyLabel(maxAmount),
            })}
          </div>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('doingfb-red-packet.forum.modal.total_count')}</label>
          <input
            className="FormControl"
            type="number"
            min="1"
            max={allowedCount}
            step="1"
            value={this.totalCount}
            oninput={(event) => {
              this.totalCount = event.target.value;
            }}
          />
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('doingfb-red-packet.forum.modal.distribution')}</label>
          <div className="DoingfbRedPacketTypeControl">
            <button
              type="button"
              className={`Button ${this.distribution === 'average' ? 'active' : ''}`}
              aria-pressed={this.distribution === 'average'}
              onclick={() => {
                this.distribution = 'average';
                m.redraw();
              }}
            >
              {app.translator.trans('doingfb-red-packet.forum.modal.average')}
            </button>
            <button
              type="button"
              className={`Button ${this.distribution === 'random' ? 'active' : ''}`}
              aria-pressed={this.distribution === 'random'}
              onclick={() => {
                this.distribution = 'random';
                m.redraw();
              }}
            >
              {app.translator.trans('doingfb-red-packet.forum.modal.random')}
            </button>
          </div>
          <div className="helpText">
            {app.translator.trans('doingfb-red-packet.forum.modal.distribution_help')}
          </div>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('doingfb-red-packet.forum.modal.greeting')}</label>
          <input
            className="FormControl"
            maxlength="120"
            value={this.greeting}
            oninput={(event) => {
              this.greeting = event.target.value;
            }}
          />
        </div>

        {balance !== null && balance !== undefined ? (
          <p className="helpText">
            {app.translator.trans('doingfb-red-packet.forum.modal.balance', {
              balance: currencyLabel(balance),
            })}
          </p>
        ) : null}

        <div className="Form-group">
          <Button className="Button Button--primary" type="submit" loading={this.loading}>
            {app.translator.trans('doingfb-red-packet.forum.modal.submit')}
          </Button>
        </div>
      </form>
    );
  }

  onsubmit(event) {
    event.preventDefault();

    if (this.loading) {
      return;
    }

    this.loading = true;

    app
      .request({
        method: 'POST',
        url: apiUrl('/doingfb-red-packets'),
        body: {
          data: {
            type: 'doingfb-red-packets',
            attributes: {
              totalAmount: Number(this.totalAmount),
              totalCount: Number(this.totalCount),
              distribution: this.distribution,
              greeting: String(this.greeting ?? ''),
            },
          },
        },
      })
      .then((payload) => {
        const packet = app.store.pushPayload(payload);
        const marker = `\n[redpacket id=${packet.id()}]\n`;
        const composer = this.attrs.composer;

        rememberPending(composer, packet.id());
        this.attrs.editor?.insertAtCursor(marker, false);
        app.alerts.show({ type: 'success' }, app.translator.trans('doingfb-red-packet.forum.created'));
        this.hide();
      })
      .catch((error) => {
        this.loading = false;
        this.onerror(error);
        m.redraw();
      });
  }
}

class ClaimedRedPacketModal extends Modal {
  className() {
    return 'DoingfbRedPacketClaimModal Modal--small';
  }

  title() {
    return app.translator.trans('doingfb-red-packet.forum.claimed_title');
  }

  content() {
    const packet = this.attrs.packet;

    return (
      <div className="Modal-body">
        <div className="DoingfbRedPacketClaimResult">
          <Icon name={redPacketIcon} />
          <strong>{currencyLabel(packet?.actorClaimAmount())}</strong>
          <p>{packet?.greeting()}</p>
          <Button className="Button Button--primary" onclick={() => this.hide()}>
            {app.translator.trans('doingfb-red-packet.forum.close')}
          </Button>
        </div>
      </div>
    );
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
    return app
      .request({
        method: 'GET',
        url: apiUrl(`/doingfb-red-packets/${this.attrs.id}`),
      })
      .then((payload) => {
        this.packet = app.store.pushPayload(payload);
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    if (this.loading) {
      return <div className="DoingfbRedPacketCard is-loading"><LoadingIndicator size="small" /></div>;
    }

    if (!this.packet) {
      return (
        <div className="DoingfbRedPacketCard is-error">
          {app.translator.trans('doingfb-red-packet.forum.load_failed')}
        </div>
      );
    }

    const packet = this.packet;
    const user = packet.user?.();
    const status = packet.status();
    const claimed = packet.claimedByActor();
    const canClaim = packet.canClaim();

    return (
      <div className={`DoingfbRedPacketCard is-${status}`}>
        <div className="DoingfbRedPacketCard-cover">
          <div className="DoingfbRedPacketCard-meta">
            {user ? user.displayName() : app.translator.trans('doingfb-red-packet.forum.user')}
          </div>
          <strong className="DoingfbRedPacketCard-greeting">{packet.greeting()}</strong>
          <div className="DoingfbRedPacketCard-art" aria-hidden="true">
            <i className={redPacketIcon} />
            <span>{app.translator.trans('doingfb-red-packet.forum.red_packet')}</span>
          </div>
          <div className="DoingfbRedPacketCard-action">
            <Button
              className="Button DoingfbRedPacketCard-openButton"
              loading={this.claiming}
              disabled={!canClaim || this.claiming}
              onclick={() => this.claim()}
            >
              {canClaim
                ? app.translator.trans('doingfb-red-packet.forum.open')
                : this.statusText(status)}
            </Button>
          </div>
          <div className="DoingfbRedPacketCard-footer">
            <strong>
              {packet.distribution() === 'random'
                ? app.translator.trans('doingfb-red-packet.forum.random')
                : app.translator.trans('doingfb-red-packet.forum.average')}
            </strong>
            <p>
              {app.translator.trans('doingfb-red-packet.forum.progress', {
                claimed: packet.claimedCount(),
                total: packet.totalCount(),
                amount: currencyLabel(packet.totalAmount()),
              })}
            </p>
            {claimed ? (
              <em>
                {app.translator.trans('doingfb-red-packet.forum.claimed_amount', {
                  amount: currencyLabel(packet.actorClaimAmount()),
                })}
              </em>
            ) : null}
          </div>
        </div>
      </div>
    );
  }

  statusText(status) {
    return app.translator.trans(`doingfb-red-packet.forum.status.${status}`);
  }

  claim() {
    if (!this.packet || this.claiming) {
      return;
    }

    this.claiming = true;

    app
      .request({
        method: 'POST',
        url: apiUrl(`/doingfb-red-packets/${this.packet.id()}/claim`),
        body: { data: { type: 'doingfb-red-packets' } },
      })
      .then((payload) => {
        this.packet = app.store.pushPayload(payload);
        this.claiming = false;
        showModal(ClaimedRedPacketModal, { packet: this.packet });
        m.redraw();
      })
      .catch((error) => {
        this.claiming = false;
        this.onerror?.(error);
        this.load();
      });
  }
}

function mountCards(root) {
  if (!root) {
    return;
  }

  root.querySelectorAll('.DoingfbRedPacketMount[data-red-packet-id]').forEach((element) => {
    if (element.dataset.mounted === '1') {
      return;
    }

    element.dataset.mounted = '1';
    m.mount(element, { view: () => <RedPacketCard id={element.dataset.redPacketId} /> });
  });
}

function replacePreviewMarkers(root) {
  const pattern = new RegExp(markerPattern.source, markerPattern.flags);
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  const textNodes = [];
  let node;

  while ((node = walker.nextNode())) {
    if (node.parentElement?.closest('.DoingfbRedPacketMount')) {
      continue;
    }

    pattern.lastIndex = 0;

    if (pattern.test(node.nodeValue || '')) {
      textNodes.push(node);
    }
  }

  textNodes.forEach((textNode) => {
    const text = textNode.nodeValue || '';
    const fragment = document.createDocumentFragment();
    let cursor = 0;
    let match;

    pattern.lastIndex = 0;

    while ((match = pattern.exec(text)) !== null) {
      if (match.index > cursor) {
        fragment.append(text.slice(cursor, match.index));
      }

      const mount = document.createElement('span');
      mount.className = 'DoingfbRedPacketMount';
      mount.dataset.redPacketId = String(match[1] || match[2]);
      fragment.append(mount);
      cursor = match.index + match[0].length;
    }

    if (cursor < text.length) {
      fragment.append(text.slice(cursor));
    }

    textNode.replaceWith(fragment);
  });
}

function refreshPreview(root) {
  replacePreviewMarkers(root);
  mountCards(root);
}

function renderComposerPreview(component, root) {
  const content = component.attrs.composer?.fields?.content?.() || '';

  if (component.redPacketPreviewContent !== content || !root.childNodes.length) {
    s9e.TextFormatter.preview(content, root);
    component.redPacketPreviewContent = content;
  }

  refreshPreview(root);
}

function schedulePreviewRefresh(component, root) {
  if (component.redPacketPreviewRefreshPending) {
    return;
  }

  component.redPacketPreviewRefreshPending = true;

  Promise.resolve().then(() => {
    component.redPacketPreviewRefreshPending = false;

    if (component.redPacketPreviewRoot !== root || !root.isConnected) {
      return;
    }

    refreshPreview(root);
  });
}

function setupPreviewObserver(component) {
  const root = component.element?.querySelector('.Split-view.Post-body');

  if (!root) {
    return;
  }

  if (component.redPacketPreviewRoot === root) {
    schedulePreviewRefresh(component, root);
    return;
  }

  teardownPreviewObserver(component);

  component.redPacketPreviewRoot = root;
  component.redPacketPreviewObserver = new MutationObserver(() => {
    schedulePreviewRefresh(component, root);
  });
  component.redPacketPreviewObserver.observe(root, {
    childList: true,
    subtree: true,
    characterData: true,
  });

  const isActive = !!component.attrs.composer?.isSplitView;
  component.element
    ?.querySelector('.TextEditor-editorContainer')
    ?.classList.toggle('is-split-view', isActive);
  root.classList.toggle('hidden', !isActive);

  if (isActive) {
    renderComposerPreview(component, root);
  }

  schedulePreviewRefresh(component, root);
}

function teardownPreviewObserver(component) {
  component.redPacketPreviewObserver?.disconnect();
  component.redPacketPreviewObserver = null;
  component.redPacketPreviewRoot = null;
}

function syncComposerPreview(component) {
  const root = component.element?.querySelector('.Split-view.Post-body');
  const container = component.element?.querySelector('.TextEditor-editorContainer');

  if (!root || !container) {
    return;
  }

  const isActive = !!component.attrs.composer?.isSplitView;
  container.classList.toggle('is-split-view', isActive);
  root.classList.toggle('hidden', !isActive);

  if (isActive) {
    renderComposerPreview(component, root);
  } else {
    component.redPacketPreviewContent = null;
  }

  setupPreviewObserver(component);
}

function toggleComposerPreview(event) {
  event?.preventDefault();
  this.composer.isSplitView = !this.composer.isSplitView;
  m.redraw();
}

function addComposerItem() {
  extend('flarum/forum/components/DiscussionComposer', 'headerItems', function (items) {
    if (!canCreateRedPacket()) {
      return;
    }

    items.add(
      'redPacket',
      <button
        type="button"
        className="Button Button--ua-reset ComposerBody-redPacket"
        onclick={() =>
          showModal(CreateRedPacketModal, {
            composer: this.composer,
            editor: this.composer.editor,
          })
        }
      >
        <span className={classList('RedPacketLabel', 'none')}>
          <Icon name={redPacketIcon} />
          {app.translator.trans('doingfb-red-packet.forum.add')}
        </span>
      </button>,
      1
    );
  });

  extend('flarum/forum/components/DiscussionComposer', 'view', function (vnode) {
    placeRedPacketAfterLottery(vnode);
  });
}

app.initializers.add('doingfb-red-packet', () => {
  app.store.models['doingfb-red-packets'] = RedPacket;
  Discussion.prototype.hasRedPacket = Model.attribute('hasRedPacket');
  addComposerItem();
  extend('flarum/forum/components/DiscussionComposer', 'oninit', function () {
    this.jumpToPreview = toggleComposerPreview;
  });

  override(ComposerState.prototype, 'clear', function (original) {
    const pending = this.redPacketPendingIds || [];

    this.redPacketPendingIds = [];
    original();
    cancelPending(pending);
  });

  extend('flarum/common/components/TextEditor', 'buildEditorParams', function (params) {
    const composer = this.attrs.composer;

    params.inputListeners.push(() => {
      syncPending(composer, this.value);
    });
  });

  extend(Post.prototype, 'oncreate', function () {
    mountCards(this.element);
  });

  extend(Post.prototype, 'onupdate', function () {
    mountCards(this.element);
  });

  extend('flarum/common/components/TextEditor', 'oncreate', function () {
    syncComposerPreview(this);
  });

  extend('flarum/common/components/TextEditor', 'onupdate', function () {
    syncComposerPreview(this);
  });

  extend('flarum/common/components/TextEditor', 'onremove', function () {
    teardownPreviewObserver(this);
  });

  extend(Discussion.prototype, 'badges', function (badges) {
    if (this.hasRedPacket?.()) {
      badges.add(
        'redPacket',
        Badge.component({
          type: 'red-packet',
          label: app.translator.trans('doingfb-red-packet.forum.red_packet'),
          icon: redPacketIcon,
        }),
        5
      );
    }
  });
});
