(function(root,factory){if(typeof module==='object'&&module.exports)module.exports=factory();else root.LPMGrid=factory();})(typeof window!=='undefined'?window:this,()=>{
    'use strict';
    function parseTSV(text){
        text=text.replace(/\r\n?/g,'\n');let row=[],rows=[],value='',quoted=false,atStart=true;
        for(let i=0;i<text.length;i++){
            const c=text[i];
            if(quoted){if(c==='"'){if(text[i+1]==='"'){value+='"';i++;}else quoted=false;}else value+=c;continue;}
            if(c==='"'&&atStart){quoted=true;atStart=false;continue;}
            if(c==='\t'||c==='\n'){row.push(value);value='';atStart=true;if(c==='\n'){rows.push(row);row=[];}continue;}
            value+=c;atStart=false;
        }
        if(quoted)throw new Error('Teks paste memiliki kutip yang belum ditutup.');
        if(value!==''||row.length||!rows.length){row.push(value);rows.push(row);}
        return rows;
    }
    function transpose(matrix){if(!matrix.length)return [];return matrix[0].map((_,i)=>matrix.map(row=>row[i]));}
    function money(text,options){
        const symbols={IDR:'Rp',USD:'US$',EUR:'€',GBP:'£',SGD:'S$',MYR:'RM',AUD:'A$',JPY:'¥'};
        const symbol=symbols[options.currency];let value=text.trim();
        for(const prefix of [options.currency,symbol])if(value.startsWith(prefix)){value=value.slice(prefix.length).trim();break;}
        const id=options.number_format==='id-ID',valid=id?/^-?(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$/:/^-?(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d{1,2})?$/;
        if(!valid.test(value))return text;
        const number=Number(value.replaceAll(id?'.':',','').replace(id?',':'.','.'));
        if(!Number.isFinite(number)||Math.abs(number)>999999999999999)return text;
        return symbol+' '+new Intl.NumberFormat(options.number_format,{minimumFractionDigits:options.decimals,maximumFractionDigits:options.decimals}).format(number);
    }
    return {parseTSV,transpose,money};
});
