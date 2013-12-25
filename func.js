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
    var albumlist = document.getElementById('albums');
    var textcell = document.getElementById('restrict_cell');
    var checkbox = document.getElementById('albums_restrict');
    var i=0;
    if (albumlist && textcell && checkbox)
    {
        if (albumlist.selectedIndex < 0)
        {
            textcell.className='restrict_cell_off';
            checkbox.disabled = 1
        }
        else
        {
            textcell.className='restrict_cell_on';
            checkbox.disabled = 0
        }
    }
}
