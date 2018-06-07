begin transaction;

create temp table entries_M as select * from entries;
drop table entries;
drop view recent_entries;

create table entries
(
  _id integer primary key,
  of_feed integer,
  feedwide_id text,
  created_at integer,
  published_at integer,
  title text,
  content text,
  link text,
  foreign key (of_feed) references feeds (_id)
);

create index entries_published on entries (published_at);
create index entries_created on entries (created_at);

insert into entries
  select _id, of_feed, feedwide_id, timestamp as created_at,
    timestamp as published_at, title, content, link from entries_M;
drop table entries_M;

create view recent_entries as
  select * from entries order by published_at desc;

commit;
