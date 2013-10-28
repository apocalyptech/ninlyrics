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
