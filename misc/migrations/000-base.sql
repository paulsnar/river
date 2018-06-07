.bail on

pragma journal_mode=wal;

begin transaction;

create table feeds
(
  _id integer primary key,
  name text,
  url text,
  type text, -- RSS or Atom
  category text,
  color text,
  favicon text,
  is_standout boolean,
  is_frontpage boolean
);

create table feed_poll_schedule
(
  _id integer primary key,
  of_feed integer,
  at integer,
  done boolean,
  foreign key (of_feed) references feeds (_id)
);

create view feed_polls_incomplete as
  select * from feed_poll_schedule
    where done != 1 and at <= strftime('%s', 'now')
    order by at asc;

create table entries
(
  _id integer primary key,
  of_feed integer,
  feedwide_id text,
  timestamp integer,
  title text,
  content text,
  link text,
  foreign key (of_feed) references feeds (_id)
);
create index entries_by_timestamp on entries (timestamp);
create view recent_entries as
  select * from entries
    order by timestamp desc;

commit;
