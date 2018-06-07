(function(window) {

  var River = { }
  if ('River' in window) {
    River = window.River
  } else {
    window.River = River
  }

  var Frontpage = River.Frontpage = { }
  var Common = River.Common

  var Feed = Backbone.Model.extend({
    idAttribute: '_id',
  })

  var Feeds = Backbone.Collection.extend({
    model: Feed,
    url: River.Config.urlbase + '1/front/feeds',
  })

  /*
    A news entry.

    Note that #.collection reflects the timeline it is a part of, however a
    #.feed value should be provided if the timeline is not equal to the feed.
  */
  var Entry = Backbone.Model.extend({
    idAttribute: '_id',

    initialize: function(attrs, opts) {
      this.feed = opts.feed
      this.feed = _.result(this, 'feed')
    },
  })

  var Timeline = Backbone.Collection.extend({
    model: Entry,
    url: River.Config.urlbase + '1/front/entries',

    comparator: function(a, b) {
      return b.get('published_at') - a.get('published_at')
    },
  })

  Frontpage.attach = function($root, kickstart) {
    var feeds = new Feeds(kickstart.feeds)

    var filter

    var entries = new Timeline(kickstart.entries, {
      model: function(attrs, opts) {
        if ( ! ('feed' in opts)) {
          opts.feed = feeds.get(attrs.of_feed)
        }
        return new Entry(attrs, opts)
      },
    })

    var timelineView = new TimelineView({
      el: $root,
      entries: entries,
      feeds: feeds,
    }).render()
    filter = timelineView.filterView

    // entries.add(kickstart.entries)

    $('[data-timestamp]').each(function() {
      var $this = $(this),
          ts = $this.data('timestamp')
      $this.text(Common.humanizeTimestamp(ts))
    })

    var _entryStaging = new Timeline(),
        _fetchNewEntryPage = function(since, done) {
          $.ajax({
            method: 'GET',
            url: River.Config.urlbase + '1/front/entries',
            data: {
              since: since,
            },
            dataType: 'json',
            success: function(newEntries) {
              done(null, newEntries)
            },
            error: function(xhr) {
              console.error('[bg] API status %d', xhr.status)
              done(xhr)
            },
          })
        }

    setInterval(function() {
      var since =
        (_entryStaging.length ?
            _entryStaging : entries).reduce(function(acc, _entry) {
          var createdAt = _entry.get('created_at')
          return (createdAt > acc) ? createdAt : acc
        }, 0)

      _fetchNewEntryPage(since, function __process(err, newEntries) {
        if (err) { return }

        _entryStaging.add(newEntries)

        if (newEntries.length === River.Config.page_size) {
          return _fetchNewEntryPage(_entryStaging.reduce(function(acc, _entry) {
            var createdAt = _entry.get('created_at')
            return (createdAt > acc) ? createdAt : acc
          }, 0), __process)
        }

        var unseenFeeds = _entryStaging.map(function(e) {
          return e.get('of_feed')
        }).reduce(function(acc, f) {
          if (acc.indexOf(f) === -1) {
            return acc.concat([ f ])
          }
          return acc
        }, [ ]).map(function(f) {
          if ( ! feeds.get(f)) {
            return f
          }
          return null
        }).reduce(function(acc, f) {
          if (f !== null) {
            return acc.concat([ f ])
          }
          return acc
        }, [ ])

        if (unseenFeeds.length !== 0) {
          feeds.fetch({
            success: timelineView.trigger.bind(timelineView,
                'new_entries', _entryStaging.length),
            error: function(xhr) {
              console.error('[bg] API status %d', xhr.status)
            },
          })
        } else {
          timelineView.trigger('new_entries', _entryStaging.length)
        }
      })
    }, 30000)

    timelineView.on('merge_new_entries', function() {
      for (var i = _entryStaging.length - 1; i >= 0; i -= 1) {
        var e = _entryStaging.at(i)
        e.feed = feeds.get(e.get('of_feed'))
        entries.add(e) // filtering occurs by event propagation
      }
      _entryStaging.reset()
      timelineView.trigger('new_entries', 0)
    })
  }

  var LOAD_MORE = 'Ielādēt vairāk',
      LOADING = 'Notiek ielāde…'

  var TimelineView = Backbone.View.extend({
    initialize: function(opts) {
      this.feeds = opts.feeds
      this.entries = opts.entries

      this.filterView = new FilterSelectionView({
        feeds: this.feeds,
        entries: this.entries,
      })

      this.feedView = new FeedView({
        el: this.$('.feed-container'),
        entries: opts.entries,
      })

      this.$loadMoreButton =
        $('<button>')
          .attr('id', 'js-tl-load-more')
          .addClass('feed-entry button')
          .text(LOAD_MORE)
      this._loadMoreButtonActive = false

      this.$newEntryIndicator =
        $('<button>')
          .attr('id', 'js-tl-unread-notification')
          .addClass('feed-unread-notification')
          .hide()

      this.listenTo(this, 'new_entries', this._displayNewEntryIndicator)
    },

    events: {
      'click button#js-tl-unread-notification': '_mergeNewEntries',
      'click button#js-tl-load-more': '_loadMoreEntries',
    },

    render: function() {
      var $feedView = this.feedView.$el

      var $filterView = this.filterView.render().$el
      $feedView.before($filterView)

      $feedView.after(this.$loadMoreButton)
      $filterView.before(this.$newEntryIndicator)

      return this
    },

    _displayNewEntryIndicator: function(n) {
      if (n < 1) {
        this.$newEntryIndicator.hide()
      } else {
        var pluralize = ! ((n % 10) === 1 && (n % 100) !== 11)
        this.$newEntryIndicator
          .show()
          .text(n + (pluralize ? ' jaunas vēstis' : ' jauna vēsts'))
      }
    },

    _mergeNewEntries: function() {
      this.trigger('merge_new_entries')
    },

    _loadMoreEntries: function(e) {
      var self = this
      if ('preventDefault' in e) { e.preventDefault() }

      if (self._loadMoreButtonActive) { return }
      self._loadMoreButtonActive = true
      self.$loadMoreButton
        .addClass('disabled')
        .text(LOADING)

      self.entries.fetch({
        remove: false,
        merge: false,
        feed: function() {
          return self.feeds.get(this.get('of_feed'))
        },
        data: {
          before: self.entries.at(-1).get('published_at'),
        },
        complete: function() {
          self.$loadMoreButton
            .removeClass('disabled')
            .text(LOAD_MORE)
          self._loadMoreButtonActive = false
        },
        error: function(xhr) {
          alert('Sorry, something is horribly wrong.')
          console.error('API status %d', xhr.status)
        },
      })
    },
  })

  var CB_OFF = 0,
      CB_ON = 1,
      CB_PARTIAL = 2

  var FilterSelectionView = Backbone.View.extend({
    className: 'fp-filter',

    initialize: function(opts) {
      this._feeds = opts.feeds
      this._entries = opts.entries

      this.listenTo(this._entries, 'add', this._mergeFilteredStatus)

      this.listenTo(this._feeds, 'add', this._handleAdd)
      this.listenTo(this._feeds, 'remove', this._handleRemove)
      this.listenTo(this._feeds, 'reset', this._handleReset)
      this.listenTo(this._feeds, 'sort', this._handleReset)

      this.feeds =
        opts.feeds
          .map(function(feed) { return feed.id })

      this.categories =
        opts.feeds
          .map(function(f) { return f.get('category') })
          .reduce(function(acc, cat) {
            if (cat === null) { return acc }
            if (acc.indexOf(cat) === -1) {
              return acc.concat([ cat ])
            }
            return acc
          }, [ ])


      this.$feeds = { }
      for (var i = 0; i < this.feeds.length; i += 1) {
        var feed = this.feeds[i],
            _feed = this._feeds.get(feed),
            view = new FilterUnitView({
              type: 'feed',
              _id: feed,
              name: _feed.get('name'),
            })
        this.listenTo(view, 'changed', this._handleUnitChanged)
        this.$feeds[feed] = view
      }

      this.$categories = { }
      for (var i = 0; i < this.categories.length; i += 1) {
        var cat = this.categories[i],
            view = new FilterUnitView({
              type: 'category',
              _id: cat
            })
        this.listenTo(view, 'changed', this._handleUnitChanged)
        this.$categories[cat] = view
      }

      this._entries.each(this._mergeFilteredStatus, this)
    },

    _handleAdd: function(_feed) {
      var feed = _feed.id,
          $feed = new FilterUnitView({
            type: 'feed',
            _id: feed,
            name: _feed.get('name'),
          })

      var lastFeed = this.feeds[this.feeds.length - 1],
          $lastFeed = this.$feeds[lastFeed]
      $lastFeed.$el.after($feed.render().el)

      this.feeds.push(feed)
      this.$feeds[feed] = $feed

      this.listenTo($feed, 'changed', this._handleUnitChanged)

      this._recheckCategories()
    },

    _handleRemove: function(_feed) {
      var feed = _feed.id,
          $feed = this.$feeds[feed]

      $feed.remove()
      delete this.$feeds[feed]
      this.feeds.splice(this.feeds.indexOf(feed), 1)

      this._recheckCategories()
    },

    _handleReset: function(_feeds) {
      var self = this

      var keep = { }, add = [ ]
      _feeds.each(function(_feed) {
        var feed = _feed.id
        if (feed in self.$feeds) {
          keep[feed] = true
        } else {
          add.push(_feed)
        }
      })

      for (var i = self.feeds.length - 1; i >= 0; i -= 1) {
        var feed = self.feeds[i]
        if ( ! (feed in keep)) {
          self.feeds.splice(i, 1)
          self.$feeds[feed].remove()
          delete self.$feeds[feed]
        }
      }

      for (var i = 0; i < add.length; i += 1) {
        self._handleAdd(add[i])
      }

      self._recheckCategories()
      self._handleSort(_feeds)
    },

    _handleSort: function(_feeds) {
      var $lastEl = this.$feeds[this.feeds[0]].$el

      this.feeds = _feeds.map(function(_feed) { return _feed.id })

      $lastEl.before(this.$feeds[this.feeds[0]].$el)
      var $lastEl = this.$feeds[this.feeds[0]].$el

      for (var i = 1; i < this.feeds.length; i += 1) {
        var $el = this.$feeds[this.feeds[i]].$el
        $lastEl.after($el)
        $lastEl = $el
      }
    },

    _recheckCategories: function() {
      var self = this

      var keep = { }, add = [ ]
      self._feeds.each(function(_feed) {
        var cat = _feed.get('category')
        if (typeof cat !== 'undefined') {
          if (cat in self.$categories) {
            keep[cat] = true
          } else {
            add.push(cat)
          }
        }
      })

      for (var i = self.categories.length - 1; i >= 0; i -= 1) {
        var cat = self.categories[i]
        if ( ! (cat in keep)) {
          self.categories.splice(i, 1)
          self.$categories[cat].remove()
          delete self.$categories[cat]
        }
      }

      for (var i = 0; i < add.length; i += 1) {
        var cat = add[i],
            $cat = new FilterUnitView({
              type: 'category',
              _id: cat,
            })

        var lastCat = this.categories[this.categories.length - 1],
            $lastCat = this.$categories[lastCat]
        $lastCat.$el.after($cat.render().el)

        this.categories.push(cat)
        this.$categories[cat] = $cat

        this.listenTo($cat, 'changed', this._handleUnitChanged)
      }
    },

    render: function() {
      this.$el.append(
        $('<span>')
          .addClass('fp-filter-label')
          .text('Filtrēt: ')
      )

      for (var i = 0; i < this.feeds.length; i += 1) {
        var feed = this.feeds[i],
            view = this.$feeds[feed]
        this.$el.append(view.render().el)
      }

      for (var i = 0; i < this.categories.length; i += 1) {
        var cat = this.categories[i],
            view = this.$categories[cat]
        this.$el.append(view.render().el)
      }

      return this
    },

    _handleUnitChanged: function(type, name, state) {
      var self = this

      if (type === 'feed') {
        var feed = self._feeds.get(name),
            cat = feed.get('category')
        if (typeof cat !== 'undefined') {
          var otherFeedStates = self._feeds.where({ category: cat })
            .map(function(feed) {
              return self.$feeds[feed.id].state
            })

          var summaryState = otherFeedStates.reduce(function(acc, st) {
            if (acc === null) {
              return st
            } else if (st === acc) {
              return acc
            } else {
              return CB_PARTIAL
            }
          }, null)
          this.$categories[cat].set(summaryState, true)
        }
        this._propagateFeedFiltered(feed.id, ! state)
      } else if (type === 'category') {
        var affectedFeeds = self._feeds.where({ category: name })
        for (var i = 0; i < affectedFeeds.length; i += 1) {
          var feed = affectedFeeds[i]
          self.$feeds[feed.id].set(state, true)
          this._propagateFeedFiltered(feed.id, ! state)
        }
      }
    },

    _propagateFeedFiltered: function(feed, filtered) {
      this._entries.each(function(entry) {
        if (entry.feed.id === feed) {
          entry.set('_filtered', filtered)
        }
      })
    },

    _mergeFilteredStatus: function(entry) {
      var filtered = ! this.$feeds[entry.feed.id].state
      entry.set('_filtered', filtered)
    },
  })

  var FilterUnitView = Backbone.View.extend({
    tagName: 'label',
    className: 'fp-filter-crit',

    initialize: function(opts) {
      this.type = opts.type
      this._id = opts._id
      this.name = opts.name || opts._id

      this.$checkbox = $('<input>')
        .prop('type', 'checkbox')
        .prop('checked', true)
        .prop('indeterminate', false)
        .on('input change', this._handleToggle.bind(this))
        .hide()

      this.$label = $('<span>')
        .addClass('fp-filter-crit-name')
        .text(this.name)

      this.set(CB_ON)
    },

    remove: function() {
      this.$checkbox.off()
      this.$label.off()

      Backbone.View.prototype.remove.call(this)
    },

    render: function() {
      this.$el.addClass(this.type)
      this.$el.append(this.$checkbox)
      this.$el.append(this.$label)

      return this
    },

    set: function(state, silent) {
      if ([ CB_OFF, CB_ON, CB_PARTIAL ].indexOf(state) === -1) {
        debugger
      }
      //
      //                | CB_OFF | CB_ON | CB_PARTIAL |
      //  --------------+--------+-------+------------+
      //        checked | false  | true  | true       |
      //  indeterminate | false  | false | true       |
      //
      this.$checkbox.prop('checked', state !== CB_OFF)
      this.$checkbox.prop('indeterminate', state === CB_PARTIAL)
      this.$el.removeClass('is-on is-off is-partial')
      this.state = state
      switch (state) {
        case CB_OFF:      this.$el.addClass('is-off');      break
        case CB_ON:       this.$el.addClass('is-on');       break
        case CB_PARTIAL:  this.$el.addClass('is-partial');  break
      }
      if ( ! silent) {
        this.trigger('changed', this.type, this._id, state)
      }

      return this
    },

    _handleToggle: function(e) {
      this.set(e.target.checked ? CB_ON : CB_OFF)
    },
  })

  var FeedView = Backbone.View.extend({
    initialize: function(opts) {
      var self = this

      self._entries = opts.entries
      self.listenTo(self._entries, 'add', self._handleAdd)
      self.listenTo(self._entries, 'remove', self._handleRemove)
      self.listenTo(self._entries, 'reset', self._handleReset)
      self.listenTo(self._entries, 'sort', self._handleSort)

      self.$entries = { }

      self.entries = self._entries.map(function(_entry) {
        var entry = _entry.id,
            $el = self.$('a.feed-entry[data-id="' + entry + '"]'),
            $entry = new EntryView({ model: _entry, el: $el })

        self.$entries[entry] = $entry
        return entry
      })
    },

    _handleAdd: function(_entry, _entries) {
      var entry = _entry.id,
          pos = _entries.indexOf(_entry),
          $entry = new EntryView({ model: _entry })

      this.$entries[entry] = $entry
      $entry.render()

      if (pos === 0) {
        this.entries.unshift(entry)
        this.$el.prepend($entry.el)
      } else {
        var prevEntry = this.entries[pos - 1],
            $prevEntry = this.$entries[prevEntry]
        $prevEntry.$el.append($entry.el)

        this.entries.splice(pos, 0, entry)
      }
    },

    _handleRemove: function(_entry, _entries) {
      var entry = _entry.id,
          pos = this.entries.indexOf(entry),
          $entry = this.$entries[entry]

      $entry.remove()
      delete this.$entries[entry]

      this.entries.splice(pos, 1)
    },

    _handleReset: function(_entries) {
      var self = this

      var keep = { }, add = [ ]
      _entries.each(function(_entry) {
        var entry = _entry.id
        if (entry in self.$entries) {
          keep[entry] = true
        } else {
          add.push(_entry)
        }
      })

      for (var i = self.entries.length - 1; i >= 0; i -= 1) {
        var entry = self.entries[i]
        if ( ! (entry in keep)) {
          self.entries.splice(i, 1)
          self.$entries[entry].remove()
          delete self.$entries[entry]
        }
      }

      for (var i = 0; i < add.length; i += 1) {
        self._handleAdd(add[i])
      }
    },

    _handleSort: function(_entries) {
      this.entries = _entries.map(function(_entry) { return _entry.id })

      var $lastEl = this.$entries[this.entries[0]].$el
      this.$el.prepend($lastEl)

      for (var i = 1; i < this.entries.length; i += 1) {
        var $el = this.$entries[this.entries[i]].$el
        $lastEl.after($el)
        $lastEl = $el
      }
    },
  })

  var EntryView = Backbone.View.extend({
    tagName: 'a',
    className: 'feed-entry',
    template: _.template([
      '<span class="feed-entry-publisher"><%- feed_name %></span>',
      '<span class="feed-entry-timestamp" data-timestamp="<%- timestamp %>">',
        '<%- timestamp_human %>',
      '</span>',
      '<span class="feed-entry-title"><%- title %></span>',
      '<span class="feed-entry-summary"><%- content %></span>',
    ].join('\n')),

    initialize: function(opts) {
      this.model = opts.model
      this.listenTo(this.model, 'change:_filtered', this._handleFiltered)
      this._handleFiltered(this.model, this.model.get('_filtered'))
    },

    render: function() {
      var tsHuman = Common.humanizeTimestamp(this.model.get('published_at'))

      var title = this.model.get('title')
      var tpl = this.template({
        feed_name: this.model.feed.get('name'),
        timestamp: this.model.get('published_at'),
        timestamp_human: tsHuman,
        title: title,
        content: Common.truncate(this.model.get('content'),
          170 - title.length),
      })
      this.$el.html(tpl)

      this.$el.data('id', this.model.id)

      this.$el.prop('href', this.model.get('link'))

      return this
    },

    _handleFiltered: function(model, filtered) {
      if (filtered) {
        this.$el.hide()
      } else {
        this.$el.show()
      }
    },
  })

})(this)
