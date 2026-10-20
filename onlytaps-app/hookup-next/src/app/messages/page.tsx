import { redirect } from 'next/navigation';
import { getUser } from '@/lib/auth';
import ChatLoader from './ChatLoader';

export const metadata = { title: 'Messages · Turnpage' };

export default async function Messages() {
  const user = await getUser();
  if (!user) redirect('/login.php');

  return (
    <>
      <link rel="stylesheet" href="/assets/css/messages.css?v=1" />
      <div className="chat-app" id="chat-app" data-csrf={user.csrf ?? ''}>
        <div className="conn-banner" id="conn-banner" hidden>Reconnecting…</div>

        <aside className="chat-side">
          <div className="chat-side-head">
            <h1>Messages</h1>
            <input type="search" id="conv-search" placeholder="Search conversations" autoComplete="off" />
          </div>
          <div className="conv-list" id="conv-list" />
          <div className="conv-empty" id="conv-empty" hidden />
        </aside>

        <section className="chat-main">
          <div className="thread-placeholder" id="thread-placeholder">
            <div className="ph-icon">💬</div>
            <h2>Your messages</h2>
            <p>Select a conversation to start chatting in real time.</p>
          </div>

          <div className="thread" id="thread" hidden>
            <header className="thread-head">
              <button type="button" className="icon-btn thread-back" id="thread-back" aria-label="Back">‹</button>
              <div className="avatar avatar-md" id="peer-avatar" />
              <div className="peer-info">
                <a className="peer-name" id="peer-name" href="#" />
                <span className="peer-status" id="peer-status" />
              </div>
              <div className="peer-menu-wrap">
                <button type="button" className="icon-btn" id="peer-menu-btn" aria-label="More">⋯</button>
                <div className="peer-menu" id="peer-menu" hidden>
                  <a id="peer-profile" href="#">View profile</a>
                  <a id="peer-report" href="/report-abuse.php">Report</a>
                  <button type="button" id="peer-block">Block user</button>
                </div>
              </div>
            </header>

            <div className="msg-scroll" id="msg-scroll">
              <button type="button" className="load-more" id="load-more" hidden>Load earlier messages</button>
              <div className="msg-list" id="msg-list" />
            </div>

            <form className="composer" id="composer" autoComplete="off">
              <textarea id="composer-input" rows={1} maxLength={2100} placeholder="Write a message…" />
              <span className="composer-count" id="composer-count" />
              <button type="submit" className="send-btn" aria-label="Send">➤</button>
            </form>
          </div>
        </section>
      </div>
      <ChatLoader />
    </>
  );
}
