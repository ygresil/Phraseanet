;
var p4 = p4 || {};

;
(function(p4, $){

  function getLevel (level) {

    level = parseInt(level);

    if(isNaN(level) || level < 1)
    {
      return 1;
    }

    return level;
  };

  function getId (level)
  {
    return 'DIALOG' + getLevel(level);
  };

  function addButtons(buttons, dialog)
  {
    if(dialog.options.closeButton === true)
    {
      buttons[language.fermer] = function() {
        dialog.Close();
      };
    }
    if(dialog.options.cancelButton === true)
    {
      buttons[language.annuler] = function() {
        dialog.Close();
      };
    }

    return buttons;
  }

  var phraseaDialog = function (options, level) {

    var createDialog = function(level) {

      var $dialog = $('#' + getId(level));

      if($dialog.length > 0)
      {
        throw 'Dialog already exists at this level';
      }

      $dialog = $('<div style="display:none;" id="' + getId(level) + '"></div>');
      $('body').append($dialog);

      return $dialog;
    }

    var defaults = {
      size : 'Medium',
      buttons : {},
      loading : true,
      title : '',
      closeOnEscape : true,
      confirmExit:false,
      closeCallback:false,
      closeButton:false,
      cancelButton:false
    },
    options = typeof options === 'object' ? options : {},
    width,
    height,
    $dialog,
    $this = this;

    this.closing = false;

    this.options = $.extend(defaults, options);

    this.level = getLevel(level);

    this.options.buttons = addButtons(this.options.buttons, this);

    switch(this.options.size)
    {
      case 'Full':
        height = bodySize.y - 30;
        width = bodySize.x - 30 ;
        break;
      case 'Medium':
        width = 730;
        height = 520;
        break;
      default:
      case 'Small':
        width = 420;
        height = 300;
        break;
      case 'Alert':
        width = 300;
        height = 150;
        break;
    }

    /*
        * 3 avaailable dimensions :
        *
        *  - Full   | Full size ()
        *  - Medium | 420 x 450
        *  - Small  | 730 x 480
        *
        **/
    this.$dialog = createDialog(this.level),
    zIndex = Math.min(this.level * 5000 + 5000, 32767);

    var CloseCallback = function() {
      if(typeof $this.options.closeCallback === 'function')
      {
        $this.options.closeCallback($this.$dialog);
      }

      if($this.closing === false)
      {
        $this.closing = true;
        $this.Close();
      }
    };

    this.$dialog.dialog('destroy').attr('title', this.options.title)
    .empty()
    .dialog({
      buttons:this.options.buttons,
      draggable:false,
      resizable:false,
      closeOnEscape:this.options.closeOnEscape,
      modal:true,
      width:width,
      height:height,
      close:CloseCallback,
      zIndex:zIndex
    })
    .dialog('open').addClass('dialog-' + this.options.size);

    if(this.options.loading === true)
    {
      this.$dialog.addClass('loading');
    }

    if(this.options.size === 'Full')
    {
      var $this = this;
      $(window).unbind('resize.DIALOG' + getLevel(level))
      .bind('resize.DIALOG' + getLevel(level), function(){
        $this.$dialog.dialog('option', {
          width : bodySize.x - 30,
          height : bodySize.y - 30
        });
      });
    }

    return this;
  };

  phraseaDialog.prototype = {
    Close : function() {
      p4.Dialog.Close(this.level);
    },
    setContent : function (content) {
      this.$dialog.removeClass('loading').empty().append(content);
    },
    getId : function () {
      return this.$dialog.attr('id');
    },
    load : function(url, method, params) {
      var $this = this;
      this.loader = {
        url : url,
        method : typeof method === 'undefined' ? 'GET' : method,
        params : typeof params === 'undefined' ? {} : params
      };

      $.ajax({
        type: this.loader.method,
        url: this.loader.url,
        dataType: 'html',
        data : this.loader.params,
        beforeSend:function(){
        },
        success: function(data){
          $this.setContent(data);
          return;
        },
        error: function(){
          return;
        },
        timeout: function(){
          return;
        }
      });
    },
    refresh : function() {
      if(typeof this.loader === 'undefined')
      {
        throw 'Nothing to refresh';
      }
      this.load(this.loader.url, this.loader.method, this.loader.params);
    },
    getDomElement : function () {
      return this.$dialog;
    },
    getOption : function (optionName) {
      return this.$dialog.dialog('option', optionName);
    },
    setOption : function (optionName, optionValue) {

      if(optionName === 'buttons')
      {
        optionValue = addButtons(optionValue, this);
      }

      this.$dialog.dialog('option', optionName, optionValue);
    }
  };

  var Dialog = function () {
    this.currentStack = {};

  };

  Dialog.prototype = {
    Create : function(options, level) {

      if(this.get(level) instanceof phraseaDialog)
      {
        this.get(level).Close();
      }

      $dialog = new phraseaDialog(options, level);

      this.currentStack[$dialog.getId()] = $dialog;

      return $dialog;
    },
    get : function (level) {

      var id = getId(level);

      if(id in this.currentStack)
      {
        return this.currentStack[id];
      }

      return null;
    },
    Close : function (level) {

      $(window).unbind('resize.DIALOG' + getLevel(level));

      this.get(level).closing = true;
      this.get(level).getDomElement().dialog('close').dialog('destroy').remove();

      var id = this.get(level).getId();

      if(id in this.currentStack)
      {
        delete this.currentStack.id;
      }
    }
  };

  p4.Dialog = new Dialog();

}(p4, jQuery));