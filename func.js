function toggleLyrics(sid)
{
    var element = document.getElementById('lyrics_' + sid);
    if (element)
    {
        if (element.style.display == 'none')
        {
            element.style.display = 'table-row';
        }
        else
        {
            element.style.display = 'none';
        }
    }
}

function checkAlbumsRestrict()
{
    // First, just the variables we'll need to turn our checkbox
    // on and off
    var albumlist = document.getElementById('albums');
    var textcell = document.getElementById('restrict_cell');
    var checkbox = document.getElementById('albums_restrict');
    var min_albums = document.getElementById('min_albums');
    var highlight_1 = document.getElementById('noresults_1');
    var highlight_2 = document.getElementById('noresults_2');
    var no_results_text = document.getElementById('no_results_text');
    var no_results_highlight = false;
    if (albumlist && textcell && checkbox && min_albums &&
            highlight_1 && highlight_2 && no_results_text)
    {
        if (albumlist.selectedIndex < 0)
        {
            textcell.className='restrict_cell_off';
            checkbox.disabled = 1
        }
        else
        {
            textcell.className='restrict_cell_on';
            checkbox.disabled = 0;

            // Check for our guaranteed-no-results conditions
            if (checkbox.checked && min_albums.value != '')
            {
                var min_albums_val = parseInt(min_albums.value);
                var albumcount = 0;
                for (var i=0; i < albumlist.length; i++)
                {
                    if (albumlist[i].selected)
                    {
                        albumcount++;
                    }
                }
                if (min_albums_val > albumcount)
                {
                    no_results_highlight = true;
                }
            }
        }

        // Do the guaranteed-no-results highlight, or not.
        if (no_results_highlight)
        {
            highlight_1.className='no_results';
            highlight_2.className='no_results';
            textcell.className += ' no_results';
            no_results_text.className='no_results no_results_text_on';
        }
        else
        {
            highlight_1.className='';
            highlight_2.className='';
            // No need to set class for textcell
            no_results_text.className='no_results_text_off';
        }
    }

}

function enableAlbumsRestrict()
{
    var checkbox = document.getElementById('albums_restrict');
    if (checkbox)
    {
        checkbox.disabled = 0;
    }
}
